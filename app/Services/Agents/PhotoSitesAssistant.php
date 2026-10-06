<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\User;
use Carbon\Carbon;

/**
 * La gestion, dans le chat, des sites autorisés pour la recherche de photos : les lister, en proposer un nouveau (qui
 * n'est ajouté qu'au clic de l'administrateur) et en retirer un. Ajouter un site donne au serveur le droit d'aller y
 * lire des pages et des images : c'est donc une proposition validée, jamais un effet de la phrase seule.
 */
class PhotoSitesAssistant
{
    public function __construct(private PhotoSites $sites, private OrchestratorInterpreter $interpreter)
    {
    }

    public function list(): array
    {
        $lines = ['• ' . JadeverPhotoSource::SITE . " — d'office — adaptateur dédié (références Jadever JD…)"];
        foreach ($this->sites->custom() as $s) {
            $lines[] = "• {$s['domain']} — " . ($s['template'] ? "recherche par modèle d'adresse, puis IA" : 'recherche par IA seulement') . ' — ajouté par ' . $s['added_by'] . ' le ' . Carbon::parse($s['added_at'])->format('d/m/Y');
        }

        return $this->reply(
            "Sites autorisés pour la recherche de photos :\n\n" . implode("\n", $lines)
            . "\n\nLes images ne sont acceptées que du domaine du site (ou de ses sous-domaines), en https, et chaque photo est montrée en aperçu avant d'être rattachée."
            . "\nPour en ajouter un : « autorise le site https://exemple.ma/recherche?q={ref} » ({ref} remplace la référence du produit) ou « autorise le site exemple.ma » (recherche par IA seulement). Pour en retirer un : « retire le site exemple.ma ».",
            [['label' => 'Chercher les photos', 'text' => 'cherche les photos des produits sans photo']],
        );
    }

    /** « autorise le site … » : prépare la proposition. @param string $text la phrase d'origine */
    public function propose(User $admin, string $text): array
    {
        $r = $this->sites->parse($text);
        if (!$r['ok']) {
            return $this->reply($r['error'], [], true);
        }

        $event = AgentEvent::create([
            'type' => 'catalogue_site', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'achats')->value('id'),
            'payload' => ['text' => "Autoriser le site {$r['domain']} pour les photos", 'domain' => $r['domain'], 'template' => $r['template'], 'requested_by' => $admin->name],
        ]);

        $how = $r['template']
            ? "Recherche : le serveur ouvre {$r['template']} (avec la référence du produit à la place de {ref}), suit le lien du produit s'il y en a un, et prend l'image principale de la page où la référence figure."
            : "Recherche : par l'IA seulement (aucun modèle d'adresse donné), limitée à ce site.";
        $ai = $this->interpreter->enabled()
            ? "\nL'IA (recherche web d'Anthropic) sert de second recours : elle reçoit la référence et le titre du produit, et les sites autorisés."
            : "\nL'IA n'est pas activée : seule la recherche par modèle d'adresse fonctionnera" . ($r['template'] ? '.' : ' (donc ce site ne servira à rien tant que l\'IA est éteinte).');

        return $this->reply(
            "Autoriser le site « {$r['domain']} » pour la recherche de photos (lot #{$event->id}) ?\n\n{$how}{$ai}\n"
            . "Garde-fous : https seulement, images du domaine {$r['domain']} (et sous-domaines) seulement, aperçu avant tout rattachement. Vous pourrez le retirer à tout moment.",
            [['label' => 'Autoriser ce site', 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            eventId: $event->id,
        );
    }

    /** Ajoute le site. @return array{body: string, meta: array<string, mixed>} */
    public function apply(User $admin, AgentEvent $event): array
    {
        $domain = (string) ($event->payload['domain'] ?? '');
        $template = $event->payload['template'] ?? null;
        if ($domain === '' || !PhotoSites::publicName($domain) || $this->sites->has($domain)) {
            return $this->reply($this->sites->has($domain) ? "Le site {$domain} est déjà autorisé." : "Cette proposition n'est plus valable : redemandez-la.", [], true, $event->id);
        }
        if (is_string($template) && !str_contains($template, '{ref}')) {
            $template = null;
        }

        $this->sites->add($domain, is_string($template) ? $template : null, $admin->name);
        $event->update(['status' => AgentEvent::STATUS_DONE]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'photo_site_added', 'level' => 'approval', 'input' => ['requested_by' => $event->payload['requested_by'] ?? null], 'result' => ['domain' => $domain, 'template' => $template]]);

        return $this->reply("Le site {$domain} est autorisé pour la recherche de photos.", [['label' => 'Chercher les photos', 'text' => 'cherche les photos des produits sans photo']], false, $event->id);
    }

    /** « retire le site exemple.ma » : retire un site ajouté (le retrait réduit les accès, il n'attend pas de validation). */
    public function remove(User $admin, string $text): array
    {
        $domain = null;
        foreach ($this->sites->domains() as $d) {
            if (stripos($text, $d) !== false) {
                $domain = $d;
                break;
            }
        }
        if ($domain === null) {
            return $this->reply('Quel site retirer ? ' . ($this->sites->domains() === [] ? "Aucun site n'a été ajouté (jadevermall.com/ma est d'office)." : 'Sites ajoutés : ' . implode(', ', $this->sites->domains()) . '.'), [], true);
        }
        $this->sites->remove($domain);
        AgentAction::create(['agent_id' => Agent::where('domain', 'achats')->value('id'), 'event_id' => null, 'action' => 'photo_site_removed', 'level' => 'approval', 'input' => ['by' => $admin->name], 'result' => ['domain' => $domain]]);

        return $this->reply("Le site {$domain} n'est plus autorisé. Les photos déjà rattachées ne sont pas touchées.");
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = [], bool $error = false, ?int $eventId = null): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'catalog', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null, 'event_id' => $eventId], fn ($v) => $v !== null)];
    }
}
