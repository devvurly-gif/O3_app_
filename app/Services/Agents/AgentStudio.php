<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentDirective;
use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * L'atelier des agents : ce que l'administrateur demande à l'orchestrateur pour faire évoluer l'équipe.
 *
 *  - recruter un agent de lecture et de rapport (AgentDesigner → fiche à valider → agent créé INACTIF) ;
 *  - le lancer, l'activer, l'arrêter ; planifier des routines (étapes connues, brouillons seulement) ;
 *  - retenir des consignes (règles de la maison) ; dire ce que sait faire chaque agent ;
 *  - rédiger une demande de développement quand la tâche n'existe pas.
 *
 * Garde-fous : un agent recruté ne lit que les domaines de données accordés et n'écrit rien ; une routine
 * n'enchaîne que des étapes qui lisent ou préparent des brouillons ; tout recrutement, routine ou consigne est
 * une proposition enregistrée au journal des agents et créée seulement au clic de l'administrateur.
 */
class AgentStudio
{
    public function __construct(
        private AgentDesigner $designer,
        private CustomAgentRunner $runner,
        private RoutineRunner $routines,
        private AgentRegistry $registry,
    ) {
    }

    // ── Recruter ─────────────────────────────────────────────────────

    public function recruit(User $admin, string $text): array
    {
        if (!$this->designer->enabled()) {
            return $this->reply("Pour recruter un agent, j'ai besoin de la compréhension avancée (IA) : activez-la sur cet écran, puis redemandez.", error: true);
        }
        $spec = $this->designer->designAgent($text);
        if ($spec === null) {
            return $this->reply("Je n'ai pas pu préparer la fiche de cet agent : " . ($this->designer->failure() ?? 'erreur') . '.', error: true);
        }
        if (!$spec['feasible']) {
            return $this->reply(
                "Je ne peux pas recruter cet agent tel quel : " . ($spec['missing'] ?? 'la mission dépasse ce que je sais lire') . ".\n"
                . "Un agent recruté lit seulement le stock, les ventes, les créances, les achats, les produits et la qualité du catalogue, et rend un rapport ; il n'écrit rien.\n"
                . 'Si la tâche manque vraiment, je peux rédiger une demande de développement.',
                suggestions: [['label' => 'Rédiger la demande de développement', 'text' => 'demande de développement : ' . mb_substr($text, 0, 200)]],
            );
        }

        $event = $this->record('agent_recrutement', 'Proposition de recrutement : ' . $spec['name'], ['spec' => $spec, 'request' => mb_substr($text, 0, 800), 'requested_by' => $admin->name]);

        $reads = collect($spec['scopes'])->map(fn ($s) => '• ' . AgentDataTools::SCOPES[$s]['label'] . ' : ' . AgentDataTools::SCOPES[$s]['reads'])->implode("\n");
        $sensitive = collect($spec['scopes'])->contains(fn ($s) => AgentDataTools::SCOPES[$s]['sensitive']);

        return $this->reply(
            "Agent proposé : « {$spec['name']} » (proposition #{$event->id}).\n\n"
            . "Mission : {$spec['mission']}\n\n"
            . "Il pourra lire :\n{$reads}\n\n"
            . ($spec['schedule'] ? 'Planification suggérée : ' . RoutineSchedule::describe($spec['schedule']) . ".\n\n" : '')
            . "Il ne peut rien écrire ni envoyer : il rend un rapport et propose des actions que vous validez. Il est créé inactif."
            . ($sensitive ? "\n\nAttention : pour analyser, les données lues (noms de clients ou fournisseurs, montants) sont envoyées à Anthropic, comme pour la lecture des documents." : ''),
            suggestions: [
                ['label' => 'Recruter cet agent (inactif)', 'text' => "applique la proposition #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore la proposition #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    public function agents(): array
    {
        $agents = Agent::where('kind', 'custom')->orderBy('id')->get();
        if ($agents->isEmpty()) {
            return $this->reply("Aucun agent recruté pour l'instant. Dites par exemple « recrute un agent qui surveille les ruptures de stock chaque matin ».");
        }

        $lines = $agents->map(function (Agent $a) {
            $last = AgentEvent::where('type', 'agent_perso_rapport')->where('agent_id', $a->id)->latest('id')->first();

            return "• #{$a->id} « {$a->name} » — " . ($a->is_active ? 'actif' : 'inactif') . ' — lit : '
                . collect($a->scopes ?? [])->map(fn ($s) => AgentDataTools::SCOPES[$s]['label'] ?? $s)->implode(', ')
                . ($last ? ' — dernier rapport ' . $last->created_at->format('d/m H:i') : '') . "\n   " . mb_strimwidth((string) $a->mission, 0, 140, '…');
        })->implode("\n");

        $suggestions = [];
        foreach ($agents->take(3) as $a) {
            $suggestions[] = $a->is_active
                ? ['label' => "Lancer « {$a->name} »", 'text' => "lance l'agent #{$a->id}"]
                : ['label' => "Activer « {$a->name} »", 'text' => "active l'agent #{$a->id}"];
        }

        return $this->reply("Agents recrutés :\n\n{$lines}", suggestions: $suggestions);
    }

    public function toggleAgent(User $admin, int $id, bool $activate): array
    {
        $agent = Agent::where('kind', 'custom')->find($id);
        if (!$agent) {
            return $this->reply("Je ne trouve pas d'agent recruté #{$id}. Les agents du socle se règlent dans l'écran Activité des agents.", error: true);
        }
        if ($agent->is_active === $activate) {
            return $this->reply("L'agent « {$agent->name} » est déjà " . ($activate ? 'actif' : 'inactif') . '.');
        }

        $agent->update(['is_active' => $activate]);
        $this->registry->syncAccountState($agent);   // le compte suit l'agent : pas de compte actif pour un agent arrêté
        // Les routines de l'agent suivent son état : elles ne tournent que lorsqu'il est actif.
        foreach (AgentRoutine::where('agent_id', $agent->id)->get() as $routine) {
            $routine->update(['is_active' => $activate, 'next_run_at' => $activate ? $routine->nextScheduledRun() : null]);
        }
        AgentTriggers::forgetListeners();
        $this->log($agent->id, $activate ? 'agent_activated' : 'agent_deactivated', ['by' => $admin->name]);

        return $this->reply(
            "Agent « {$agent->name} » " . ($activate ? 'activé' : 'désactivé') . '.' . ($activate ? ' Ses routines planifiées sont réactivées.' : ' Ses routines sont suspendues.'),
            suggestions: $activate ? [['label' => 'Le lancer maintenant', 'text' => "lance l'agent #{$agent->id}"]] : [],
        );
    }

    /** « lance l'agent #3 » ou « lance l'agent #3 : vérifie le dépôt principal ». */
    public function runAgent(User $admin, int $id, string $text): array
    {
        $agent = Agent::where('kind', 'custom')->find($id);
        if (!$agent) {
            return $this->reply("Je ne trouve pas d'agent recruté #{$id}.", error: true);
        }
        if (!$agent->is_active) {
            return $this->reply("L'agent « {$agent->name} » est inactif : activez-le d'abord.", suggestions: [['label' => 'Activer l\'agent', 'text' => "active l'agent #{$agent->id}"]], error: true);
        }

        $task = (string) $agent->mission;
        if (str_contains($text, ':')) {
            $after = trim(mb_substr($text, (int) mb_strpos($text, ':') + 1));
            $after !== '' && $task = $after;
        }
        $result = $this->runner->run($agent, $task);
        if ($result === null) {
            return $this->reply("L'agent « {$agent->name} » n'a pas pu travailler : " . ($this->runner->failure() ?? 'erreur') . '.', error: true);
        }

        $event = AgentEvent::create([
            'type' => 'agent_perso_rapport', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_DONE, 'agent_id' => $agent->id,
            'payload' => ['text' => "Rapport de l'agent {$agent->name}", 'task' => $task, 'report' => $result['report'], 'level' => $result['level'], 'tools' => $result['tools'], 'requested_by' => $admin->name],
        ]);
        $this->log($agent->id, 'agent_report', ['event' => $event->id, 'tools' => $result['tools']]);

        return $this->reply(
            "Agent « {$agent->name} » — rapport" . ($result['level'] === 'attention' ? ' (à surveiller)' : '') . " :\n\n{$result['report']}\n\nRien n'a été modifié : l'agent a seulement lu les données.",
            suggestions: array_map(fn (string $key) => ['label' => RoutineSteps::KNOWN[$key]['label'], 'text' => RoutineSteps::KNOWN[$key]['phrase']], $result['proposals']),
            eventId: $event->id,
        );
    }

    // ── Routines ─────────────────────────────────────────────────────

    public function routineNew(User $admin, string $text): array
    {
        if (!$this->designer->enabled()) {
            return $this->reply("Pour planifier une routine, j'ai besoin de la compréhension avancée (IA) : activez-la sur cet écran, puis redemandez.", error: true);
        }
        $spec = $this->designer->designRoutine($text);
        if ($spec === null) {
            return $this->reply("Je n'ai pas pu préparer la routine : " . ($this->designer->failure() ?? 'erreur') . '.', error: true);
        }
        if (!$spec['feasible']) {
            return $this->reply(
                'Je ne peux pas planifier cette routine telle quelle : ' . ($spec['missing'] ?? 'étape inconnue') . ".\n"
                . "Une routine enchaîne seulement des étapes que je connais (contrôle des encaissements, inventaire en brouillon, contrôle des fiches, état des agents…) ou un agent recruté, à heure fixe. Dites-moi aussi quand elle doit tourner (« chaque lundi à 8 h »).",
                suggestions: [['label' => 'Rédiger la demande de développement', 'text' => 'demande de développement : ' . mb_substr($text, 0, 200)]],
            );
        }

        $event = $this->record('routine_proposition', 'Proposition de routine : ' . $spec['name'], ['spec' => $spec, 'request' => mb_substr($text, 0, 800), 'requested_by' => $admin->name]);
        $steps = collect($spec['steps'])->map(fn ($s, $i) => ($i + 1) . '. ' . RoutineSteps::label($s) . (isset(RoutineSteps::KNOWN[$s]) ? ' : ' . RoutineSteps::KNOWN[$s]['does'] : ''))->implode("\n");

        return $this->reply(
            "Routine proposée : « {$spec['name']} » (proposition #{$event->id}).\n\nQuand : " . RoutineSchedule::describe($spec['schedule']) . ".\n\nÉtapes :\n{$steps}\n\n"
            . "Elle ne fait que lire ou préparer des brouillons : rien n'est appliqué ni envoyé sans votre validation. Le compte rendu arrive dans cette conversation.",
            suggestions: [
                ['label' => 'Planifier cette routine', 'text' => "applique la proposition #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore la proposition #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    /** « active / désactive l'e-mail des routines » : l'e-mail d'alerte à l'administrateur (la cloche reste). */
    public function routineEmail(User $admin, bool $on): array
    {
        Setting::set('agents', 'routine_email', $on ? 'true' : 'false');
        $this->log(null, 'routine_email_' . ($on ? 'on' : 'off'), ['by' => $admin->name]);

        return $this->reply($on
            ? "L'e-mail d'alerte des routines est activé : vous en recevrez au plus un par routine et par 6 heures, seulement quand elle a quelque chose à valider ou a échoué (en plus de la cloche)."
            : "L'e-mail d'alerte des routines est désactivé. La cloche (et la notification push si elle est configurée) continuent de vous prévenir.");
    }

    public function routines(): array
    {
        $routines = AgentRoutine::orderBy('id')->get();
        if ($routines->isEmpty()) {
            return $this->reply("Aucune routine planifiée. Dites par exemple « chaque lundi à 8 h, contrôle les encaissements et prépare les relances ».");
        }

        $lines = $routines->map(fn (AgentRoutine $r) => "• #{$r->id} « {$r->name} » — " . ($r->isEventDriven() ? AgentTriggers::describe($r->trigger) : RoutineSchedule::describe($r->schedule)) . ' — ' . ($r->is_active ? 'active' : 'en pause')
            . ($r->is_active && $r->next_run_at ? ', prochaine : ' . RoutineSchedule::display($r->next_run_at) : '')
            . ($r->last_run_at ? ', dernière : ' . $r->last_run_at->format('d/m H:i') . " ({$r->last_status})" : '')
            . "\n   " . collect($r->steps)->map(fn ($s) => RoutineSteps::label($s))->implode(' → '))->implode("\n");

        $suggestions = [];
        foreach ($routines->take(2) as $r) {
            $suggestions[] = ['label' => "Lancer « {$r->name} » maintenant", 'text' => "lance la routine #{$r->id}"];
            $suggestions[] = $r->is_active ? ['label' => 'Mettre en pause', 'text' => "mets en pause la routine #{$r->id}"] : ['label' => 'Reprendre', 'text' => "reprends la routine #{$r->id}"];
        }

        return $this->reply("Routines :\n\n{$lines}", suggestions: $suggestions);
    }

    /** @param string $verb run | pause | resume | delete */
    public function routineAction(User $admin, int $id, string $verb): array
    {
        $routine = AgentRoutine::find($id);
        if (!$routine) {
            return $this->reply("Je ne trouve pas la routine #{$id}.", error: true);
        }

        switch ($verb) {
            case 'run':
                $out = $this->routines->run($routine, 'lancée à la demande');

                return $this->reply($out['body'], error: $out['status'] === 'error');
            case 'pause':
                $routine->update(['is_active' => false, 'next_run_at' => null]);
                AgentTriggers::forgetListeners();
                $this->log($routine->agent_id, 'routine_paused', ['routine' => $id, 'by' => $admin->name]);

                return $this->reply("Routine « {$routine->name} » mise en pause.", suggestions: [['label' => 'Reprendre', 'text' => "reprends la routine #{$id}"]]);
            case 'resume':
                // Une routine à l'événement reprend à partir de maintenant : les événements passés pendant la pause sont ignorés.
                $routine->update(['is_active' => true, 'next_run_at' => $routine->nextScheduledRun(), 'last_event_id' => $routine->isEventDriven() ? (int) AgentEvent::max('id') : $routine->last_event_id]);
                AgentTriggers::forgetListeners();
                $this->log($routine->agent_id, 'routine_resumed', ['routine' => $id, 'by' => $admin->name]);

                return $this->reply("Routine « {$routine->name} » reprise : " . ($routine->isEventDriven() ? 'elle se déclenchera au prochain événement.' : 'prochaine exécution ' . RoutineSchedule::display($routine->next_run_at) . '.'));
            default:
                $name = $routine->name;
                $routine->delete();
                AgentTriggers::forgetListeners();
                $this->log($routine->agent_id, 'routine_deleted', ['routine' => $id, 'name' => $name, 'by' => $admin->name]);

                return $this->reply("Routine « {$name} » supprimée.");
        }
    }

    // ── Consignes ────────────────────────────────────────────────────

    public function directiveNew(User $admin, string $text): array
    {
        $body = trim(preg_replace('/^\s*(retiens|souviens-toi|consigne|r[eè]gle( de la maison)?|d[eé]sormais|note que|n\'oublie pas)\s*(que|:|-)?\s*/iu', '', $text) ?? '');
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
        if (mb_strlen($body) < 8) {
            return $this->reply("Dites-moi la règle en une phrase, par exemple « retiens : marge minimale de 20 % sur tous les produits ».", error: true);
        }
        $body = mb_substr($body, 0, 400);

        $event = $this->record('consigne_proposition', 'Proposition de consigne', ['body' => $body, 'requested_by' => $admin->name]);

        return $this->reply(
            "Consigne proposée (proposition #{$event->id}) :\n« {$body} »\n\nElle sera lue par les agents que vous recrutez et par les routines. Les agents du socle (inventaire, relances, fiches produits) suivent leurs propres seuils et règles : cette consigne ne les change pas.",
            suggestions: [
                ['label' => 'Retenir cette consigne', 'text' => "applique la proposition #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore la proposition #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    public function directives(): array
    {
        $all = AgentDirective::where('is_active', true)->orderBy('id')->get();
        if ($all->isEmpty()) {
            return $this->reply("Aucune consigne retenue. Dites par exemple « retiens : ne jamais relancer un client en litige ».");
        }

        return $this->reply("Consignes retenues :\n\n" . $all->map(fn (AgentDirective $d) => "• #{$d->id} {$d->body}")->implode("\n") . "\n\nPour en retirer une : « oublie la consigne #N ».");
    }

    public function directiveRemove(User $admin, int $id): array
    {
        $d = AgentDirective::where('is_active', true)->find($id);
        if (!$d) {
            return $this->reply("Je ne trouve pas la consigne #{$id}.", error: true);
        }
        $d->update(['is_active' => false]);
        $this->log(null, 'directive_removed', ['directive' => $id, 'by' => $admin->name]);

        return $this->reply("Consigne #{$id} retirée : « {$d->body} ».");
    }

    // ── Catalogue des tâches, demandes de développement ──────────────

    public function catalogue(): array
    {
        $agents = Agent::where('kind', 'builtin')->orderBy('id')->get()->keyBy('domain');
        $blocks = [];
        foreach (AgentCapabilities::all() as $domain => $cap) {
            $agent = $agents->get($domain);
            $head = '• ' . ($agent?->name ?? ucfirst($domain)) . ($agent ? ($agent->is_active ? ' (actif)' : ' (inactif)') : '');
            $tasks = $cap['tasks'] === [] ? "\n   Aucune tâche pour l'instant." : "\n" . collect($cap['tasks'])->map(fn ($t) => "   – {$t[0]} — {$t[1]}")->implode("\n");
            $missing = $cap['missing'] === [] ? '' : "\n   Pas encore : " . implode(' ; ', $cap['missing']) . '.';
            $blocks[] = $head . $tasks . $missing;
        }

        $custom = Agent::where('kind', 'custom')->count();
        $routines = AgentRoutine::where('is_active', true)->count();
        $general = collect(AgentCapabilities::GENERAL)->map(fn ($t) => "   – {$t[0]} — {$t[1]}")->implode("\n");

        $suggestions = [];
        foreach (array_keys(AgentCapabilities::all()) as $domain) {
            if (AgentCapabilities::all()[$domain]['tasks'] === [] && count($suggestions) < 3) {
                $name = $agents->get($domain)?->name ?? ucfirst($domain);
                $suggestions[] = ['label' => "Demander le développement : {$name}", 'text' => "demande de développement pour l'agent {$name}"];
            }
        }

        return $this->reply(
            "Ce que sait faire chaque agent aujourd'hui :\n\n" . implode("\n\n", $blocks)
            . "\n\nL'orchestrateur lui-même :\n{$general}"
            . "\n\nAgents recrutés : {$custom}. Routines actives : {$routines}."
            . "\n\nUne tâche qui manque ? Dites « demande de développement : … » et je rédige la demande à transmettre au développeur.",
            suggestions: $suggestions,
        );
    }

    /** Rédige, enregistre et affiche une demande de développement : un texte complet à transmettre au développeur. */
    public function devRequest(User $admin, string $text): array
    {
        $about = trim(preg_replace('/^\s*(demande de d[eé]veloppement|nouvelle (t[aâ]che|fonction|capacit[eé])|il faudrait que tu saches)\s*(pour|:|-)?\s*/iu', '', $text) ?? '');
        $about = mb_substr($about, 0, 600);
        if (mb_strlen($about) < 6) {
            return $this->reply("Décrivez la tâche en une ou deux phrases, par exemple « demande de développement : préparer les livraisons du jour à partir des bons de livraison confirmés ».", error: true);
        }

        $ideas = '';
        foreach (Agent::where('kind', 'builtin')->get() as $agent) {
            if (mb_stripos($about, $agent->name) !== false && isset(AgentCapabilities::all()[$agent->domain])) {
                $ideas = "\nIdées déjà identifiées pour cet agent : " . implode(' ; ', AgentCapabilities::all()[$agent->domain]['missing']) . '.';
            }
        }

        $event = $this->recordDevRequest($admin, $about, $ideas);

        return $this->reply(
            "Demande de développement #{$event->id} enregistrée. Copiez le texte ci-dessous et transmettez-le au développeur (Claude Code) :\n\n{$event->payload['brief']}",
            suggestions: [['label' => 'Voir les demandes ouvertes', 'text' => 'mes demandes de développement']],
            eventId: $event->id,
        );
    }

    /**
     * Rédige et enregistre une demande de développement (texte complet à transmettre au développeur).
     *
     * @param string $ideas texte additionnel (idées déjà identifiées, contexte d'une conception…)
     */
    public function recordDevRequest(User $admin, string $about, string $ideas = ''): AgentEvent
    {
        $brief = "DEMANDE DE DÉVELOPPEMENT — " . mb_strimwidth($about, 0, 70, '…') . "\n\n"
            . "Contexte : application O3, orchestrateur des agents IA (voir la « Spécification du socle des agents IA »).\n"
            . 'Besoin exprimé par ' . $admin->name . ' le ' . now()->format('d/m/Y') . " :\n« {$about} »{$ideas}\n\n"
            . "À respecter (principes du socle) :\n"
            . "- l'agent prépare des brouillons ou des propositions ; rien ne s'écrit sans validation d'un humain (bouton dans l'orchestrateur) ;\n"
            . "- lecture seule par défaut, chaque action enregistrée au journal des agents ;\n"
            . "- tests automatiques, contrôle PHPStan, puis déploiement seulement sur demande explicite.\n\n"
            . "À préciser avant de commencer : les données à lire, les règles de gestion et les seuils, le résultat attendu, qui valide et à quelle fréquence.";

        return AgentEvent::create([
            'type' => 'demande_developpement', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_NEW,
            'payload' => ['text' => 'Demande de développement : ' . mb_strimwidth($about, 0, 80, '…'), 'brief' => $brief, 'requested_by' => $admin->name],
        ]);
    }

    public function devRequests(): array
    {
        $open = AgentEvent::where('type', 'demande_developpement')->where('status', AgentEvent::STATUS_NEW)->orderBy('id')->get();
        if ($open->isEmpty()) {
            return $this->reply('Aucune demande de développement ouverte.');
        }

        return $this->reply("Demandes de développement ouvertes :\n\n" . $open->map(fn (AgentEvent $e) => "• #{$e->id} — " . ($e->payload['text'] ?? '') . ' (' . $e->created_at->format('d/m') . ')')->implode("\n")
            . "\n\nQuand une demande est traitée : « la demande #N est faite ».", suggestions: $open->take(3)->map(fn (AgentEvent $e) => ['label' => "Demande #{$e->id} faite", 'text' => "la demande #{$e->id} est faite"])->all());
    }

    public function devRequestDone(int $id): array
    {
        $e = AgentEvent::where('type', 'demande_developpement')->where('status', AgentEvent::STATUS_NEW)->find($id);
        if (!$e) {
            return $this->reply("Je ne trouve pas de demande ouverte #{$id}.", error: true);
        }
        $e->update(['status' => AgentEvent::STATUS_DONE]);

        return $this->reply("Demande #{$id} marquée comme faite.");
    }

    // ── Validation des propositions ──────────────────────────────────

    /**
     * « applique la proposition #12 », « ignore la proposition #12 ». Une proposition ne se traite qu'une fois, même si le
     * bouton est cliqué deux fois en même temps (même réservation que CatalogAssistant::act, voir LotClaim).
     *
     * @param string $n phrase normalisée
     */
    public function act(User $admin, int $eventId, string $n): array
    {
        if (!LotClaim::take($eventId)) {
            return LotClaim::isBeingProcessed($eventId)
                ? $this->reply("La proposition #{$eventId} est déjà en cours de traitement : patientez quelques secondes.", eventId: $eventId)
                : $this->actOnce($admin, $eventId, $n);
        }

        try {
            return $this->actOnce($admin, $eventId, $n);
        } finally {
            LotClaim::release($eventId);
        }
    }
    /** @param string $n phrase normalisée */
    private function actOnce(User $admin, int $eventId, string $n): array
    {
        $event = AgentEvent::whereIn('type', ['agent_recrutement', 'routine_proposition', 'consigne_proposition', 'comptes_agents', 'conception_proposition'])->find($eventId);
        if (!$event) {
            return $this->reply("Je ne trouve pas la proposition #{$eventId}.", error: true);
        }
        if (!in_array($event->status, [AgentEvent::STATUS_ROUTED, AgentEvent::STATUS_IN_PROGRESS], true)) {
            return $this->reply("La proposition #{$eventId} a déjà été traitée ou ignorée.", eventId: $eventId);
        }
        if (preg_match('/ignor|annul|abandon/', $n)) {
            $event->update(['status' => AgentEvent::STATUS_REJECTED]);
            $this->closeInterview($event, AgentEvent::STATUS_REJECTED);

            return $this->reply("C'est noté : la proposition #{$eventId} est ignorée, rien n'a été créé.", eventId: $eventId);
        }
        if (!preg_match('/appliqu|confirm|valid|lance|recrut|planifi|retiens/', $n)) {
            return $this->reply("Proposition #{$eventId} en attente : dites « applique la proposition #{$eventId} » ou « ignore la proposition #{$eventId} ».", eventId: $eventId);
        }

        $payload = $event->payload ?? [];
        $reply = match ($event->type) {
            'agent_recrutement'   => $this->createAgent($admin, $event, $payload['spec'] ?? []),
            'routine_proposition' => $this->createRoutine($admin, $event, $payload['spec'] ?? []),
            'comptes_agents'      => $this->createAccounts($admin, $event),
            'conception_proposition' => $this->applyDesign($admin, $event),
            default               => $this->createDirective($admin, $event, (string) ($payload['body'] ?? '')),
        };
        if (!($reply['meta']['error'] ?? false)) {
            $event->update(['status' => AgentEvent::STATUS_DONE]);
            $this->closeInterview($event, AgentEvent::STATUS_DONE);
        }

        return $reply;
    }

    /** Un plan de conception validé ou ignoré clôt l'entretien dont il est issu. */
    private function closeInterview(AgentEvent $proposal, string $status): void
    {
        if ($proposal->type === 'conception_proposition' && !empty($proposal->payload['interview_id'])) {
            AgentEvent::where('type', 'atelier_entretien')->whereKey((int) $proposal->payload['interview_id'])->update(['status' => $status]);
        }
    }

    /**
     * Crée ce que le plan de l'entretien prévoit : l'agent (inactif, avec son compte), ses routines (horaire et/ou
     * événement interne) et une demande de développement par besoin hors du socle. Tout est re-nettoyé ici :
     * ce qui a disparu depuis la proposition (étape, domaine) est écarté.
     */
    private function applyDesign(User $admin, AgentEvent $event): array
    {
        $spec = $event->payload['spec'] ?? [];
        $name = (string) ($spec['name'] ?? '');
        if ($name === '') {
            return $this->reply('Ce plan est incomplet : relancez l\'entretien.', error: true, eventId: $event->id);
        }

        $done = [];
        $agent = null;
        $a = $spec['agent'] ?? null;
        if (is_array($a)) {
            $scopes = AgentDataTools::sanitizeScopes($a['scopes'] ?? []);
            if ($scopes !== [] && ($a['name'] ?? '') !== '' && ($a['mission'] ?? '') !== '') {
                $agent = $this->makeAgent($admin, $a['name'], $a['mission'], $scopes, $event->id);
                $done[] = "• Agent #{$agent->id} « {$agent->name} » recruté (inactif), avec son propre compte";
            }
        }

        $steps = RoutineSteps::sanitize($spec['steps'] ?? []);
        $agent && array_unshift($steps, "agent:{$agent->id}");
        $schedule = RoutineSchedule::sanitize($spec['schedule'] ?? null);
        $trigger = AgentTriggers::sanitize($spec['trigger'] ?? null);
        $routineName = 'Routine « ' . mb_strimwidth($name, 0, 60, '…') . ' »';
        $routines = [];

        // Les routines d'un agent neuf attendent son activation ; sans agent (étapes connues seulement), elles démarrent.
        if ($steps !== [] && $schedule) {
            $r = AgentRoutine::create([
                'name' => $routineName, 'steps' => $steps, 'schedule' => $schedule, 'agent_id' => $agent?->id,
                'is_active' => $agent === null, 'created_by' => $admin->id, 'next_run_at' => $agent === null ? RoutineSchedule::next($schedule) : null,
            ]);
            $routines[] = $r;
            $done[] = "• Routine #{$r->id} : " . RoutineSchedule::describe($schedule);
        }
        if ($steps !== [] && $trigger) {
            $r = AgentRoutine::create([
                'name' => $routineName . ' (événement)', 'steps' => $steps, 'schedule' => ['frequency' => 'event'], 'trigger' => $trigger,
                'last_event_id' => (int) AgentEvent::max('id'), 'agent_id' => $agent?->id, 'is_active' => $agent === null,
                'created_by' => $admin->id, 'next_run_at' => null,
            ]);
            $routines[] = $r;
            $done[] = "• Routine #{$r->id} : " . AgentTriggers::describe($trigger);
        }
        foreach ($routines as $r) {
            $this->log($agent?->id, 'routine_created', ['routine' => $r->id, 'by' => $admin->name, 'event' => $event->id]);
        }
        $routines !== [] && AgentTriggers::forgetListeners();
        $agent && $routines !== [] && $done[] = "  (les routines de l'agent démarrent quand vous l'activez)";

        $goal = (string) ($event->payload['goal'] ?? $name);
        foreach ($spec['dev_needs'] ?? [] as $d) {
            if (($d['title'] ?? '') === '') {
                continue;
            }
            $dev = $this->recordDevRequest($admin, (string) $d['title'], "\nDétail : " . ($d['detail'] ?? '') . "\nContexte : issu de l'entretien de conception « " . mb_strimwidth($goal, 0, 120, '…') . " » (plan #{$event->id}).");
            $done[] = "• Demande de développement #{$dev->id} : {$d['title']}";
        }

        if ($done === []) {
            return $this->reply('Ce plan ne contient rien de créable (agent, étapes ou besoins) : relancez l\'entretien.', error: true, eventId: $event->id);
        }
        $this->log($agent?->id, 'design_applied', ['plan' => $event->id, 'by' => $admin->name, 'name' => $name]);

        return $this->reply(
            "Plan « {$name} » mis en place :\n" . implode("\n", $done),
            suggestions: $agent ? [['label' => "Activer l'agent", 'text' => "active l'agent #{$agent->id}"]] : [],
            eventId: $event->id,
        );
    }

    private function createAgent(User $admin, AgentEvent $event, array $spec): array
    {
        $scopes = AgentDataTools::sanitizeScopes($spec['scopes'] ?? []);
        if (($spec['name'] ?? '') === '' || ($spec['mission'] ?? '') === '' || $scopes === []) {
            return $this->reply('La fiche de cet agent est incomplète : redemandez le recrutement.', error: true, eventId: $event->id);
        }

        $agent = $this->makeAgent($admin, $spec['name'], $spec['mission'], $scopes, $event->id);

        $routineNote = '';
        if (!empty($spec['schedule'])) {
            AgentRoutine::create([
                'name' => 'Routine « ' . $agent->name . ' »', 'steps' => ["agent:{$agent->id}"], 'schedule' => $spec['schedule'],
                'agent_id' => $agent->id, 'is_active' => false, 'created_by' => $admin->id,
            ]);
            $routineNote = ' Sa routine (' . RoutineSchedule::describe($spec['schedule']) . ') démarrera à son activation.';
        }

        return $this->reply(
            "Agent #{$agent->id} « {$agent->name} » recruté, inactif." . $routineNote . "\nActivez-le quand vous voulez : il lira " . collect($scopes)->map(fn ($s) => mb_strtolower(AgentDataTools::SCOPES[$s]['label']))->implode(', ') . ' et rendra un rapport.',
            suggestions: [['label' => 'Activer l\'agent', 'text' => "active l'agent #{$agent->id}"]],
            eventId: $event->id,
        );
    }

    /**
     * Crée un agent recruté : INACTIF, avec son propre compte (lui aussi inactif) et une ligne au journal.
     *
     * @param array<int, string> $scopes domaines de données déjà validés
     */
    public function makeAgent(User $admin, string $name, string $mission, array $scopes, ?int $eventId = null): Agent
    {
        $base = 'perso-' . Str::limit(Str::slug($name), 20, '');
        $domain = $base;
        for ($i = 2; Agent::where('domain', $domain)->exists(); $i++) {
            $domain = "{$base}-{$i}";
        }

        $agent = Agent::create([
            'domain' => $domain, 'name' => $name, 'kind' => 'custom', 'mission' => $mission, 'scopes' => $scopes,
            'created_by' => $admin->id, 'default_level' => 'approval', 'is_active' => false,
        ]);
        $this->log($agent->id, 'agent_recruited', ['by' => $admin->name, 'scopes' => $scopes, 'event' => $eventId]);
        // Chaque agent recruté a son propre compte, dès son recrutement (inactif comme lui).
        $account = $this->registry->ensureAccount($agent);
        $account && $this->log($agent->id, 'agent_account_created', ['email' => $account->email, 'by' => $admin->name]);

        return $agent;
    }

    private function createRoutine(User $admin, AgentEvent $event, array $spec): array
    {
        $steps = RoutineSteps::sanitize($spec['steps'] ?? []);
        $schedule = RoutineSchedule::sanitize($spec['schedule'] ?? null);
        if ($steps === [] || $schedule === null || ($spec['name'] ?? '') === '') {
            return $this->reply('La routine est incomplète ou une étape n\'existe plus : redemandez-la.', error: true, eventId: $event->id);
        }

        $routine = AgentRoutine::create([
            'name' => $spec['name'], 'steps' => $steps, 'schedule' => $schedule, 'is_active' => true,
            'created_by' => $admin->id, 'next_run_at' => RoutineSchedule::next($schedule),
        ]);
        $this->log(null, 'routine_created', ['routine' => $routine->id, 'by' => $admin->name, 'event' => $event->id]);

        return $this->reply(
            "Routine #{$routine->id} « {$routine->name} » planifiée : " . RoutineSchedule::describe($schedule) . ', prochaine exécution ' . RoutineSchedule::display($routine->next_run_at) . '.',
            suggestions: [['label' => 'La lancer maintenant', 'text' => "lance la routine #{$routine->id}"], ['label' => 'Mettre en pause', 'text' => "mets en pause la routine #{$routine->id}"]],
            eventId: $event->id,
        );
    }

    private function createDirective(User $admin, AgentEvent $event, string $body): array
    {
        if (mb_strlen($body) < 8) {
            return $this->reply('La consigne est vide : redites-la.', error: true, eventId: $event->id);
        }
        $d = AgentDirective::create(['body' => $body, 'is_active' => true, 'created_by' => $admin->id]);
        $this->log(null, 'directive_added', ['directive' => $d->id, 'by' => $admin->name]);

        return $this->reply("Consigne #{$d->id} retenue : « {$body} ».", eventId: $event->id);
    }

    // ── Comptes utilisateurs des agents ──────────────────────────────

    /** « crée les comptes des agents » : propose un compte pour chaque agent qui n'en a pas. */
    public function accountsNew(User $admin): array
    {
        $missing = $this->registry->agentsWithoutAccount();
        if ($missing->isEmpty()) {
            return $this->accounts('Tous les agents ont déjà leur compte.');
        }

        $event = $this->record('comptes_agents', 'Proposition de création de ' . $missing->count() . " compte(s) d'agents", [
            'agent_ids' => $missing->pluck('id')->all(), 'requested_by' => $admin->name,
        ]);
        $lines = $missing->map(fn (Agent $a) => "• {$a->name} — " . AgentRegistry::emailFor($a) . ' — ' . ($a->is_active ? 'compte actif' : 'compte inactif (agent inactif)'))->implode("\n");

        return $this->reply(
            $missing->count() . " compte(s) à créer (proposition #{$event->id}) :\n\n{$lines}\n\n"
            . "Chaque agent a son propre compte, indépendant des autres. Le rôle « Agent IA » n'a aucune permission ; le mot de passe est aléatoire et personne ne le connaît : le compte ne peut pas se connecter à l'interface. Aucun jeton n'est émis : un jeton ne se crée que si un agent externe en a besoin.",
            suggestions: [
                ['label' => 'Créer ces comptes', 'text' => "applique la proposition #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore la proposition #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    /** « quels agents ont un compte ? » */
    public function accountsList(): array
    {
        $agents = Agent::orderBy('id')->get();
        $users = User::withTrashed()->with('role:id,name')->whereIn('id', $agents->pluck('user_id')->filter())->get()->keyBy('id');
        $lines = $agents->map(function (Agent $a) use ($users) {
            $u = $a->user_id ? $users->get($a->user_id) : null;

            return "• {$a->name} — " . ($u ? "{$u->email} (rôle " . ($u->role?->name ?? '?') . ($u->is_active ? ', actif' : ', inactif') . ')' : 'aucun compte');
        })->implode("\n");

        return $this->accounts("Comptes des agents :\n\n{$lines}");
    }

    private function accounts(string $body): array
    {
        $missing = $this->registry->agentsWithoutAccount();

        return $this->reply($body, suggestions: $missing->isEmpty() ? [] : [['label' => 'Créer les comptes manquants', 'text' => 'crée les comptes des agents']]);
    }

    private function createAccounts(User $admin, AgentEvent $event): array
    {
        $created = [];
        foreach (Agent::whereIn('id', $event->payload['agent_ids'] ?? [])->whereNull('user_id')->orderBy('id')->get() as $agent) {
            $user = $this->registry->ensureAccount($agent);
            if ($user) {
                $created[] = "• {$agent->name} — {$user->email}";
                $this->log($agent->id, 'agent_account_created', ['email' => $user->email, 'by' => $admin->name]);
            }
        }

        return $this->reply(
            $created === [] ? 'Aucun compte à créer : les agents concernés en ont déjà un.' : count($created) . " compte(s) créé(s) :\n\n" . implode("\n", $created) . "\n\nAucun jeton n'a été émis et aucun mot de passe n'est connu : ces comptes servent d'identité aux agents (journal, audit), pas à se connecter.",
            eventId: $event->id,
        );
    }

    // ── Outils ───────────────────────────────────────────────────────

    private function record(string $type, string $text, array $payload): AgentEvent
    {
        return AgentEvent::create(['type' => $type, 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'payload' => array_merge(['text' => $text], $payload)]);
    }

    private function log(?int $agentId, string $action, array $result): void
    {
        AgentAction::create([
            'agent_id' => $agentId ?? Agent::orderBy('id')->value('id') ?? 0,
            'event_id' => null,
            'action'   => $action,
            'level'    => 'approval',
            'input'    => null,
            'result'   => $result,
        ]);
    }

    /**
     * @param array<int, array{label: string, to: string}> $links
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'      => 'studio',
            'links'       => $links ?: null,
            'error'       => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
        ], fn ($v) => $v !== null)];
    }
}
