<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\BulkSalePriceUpdater;
use App\Services\CacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * « Mettre à jour les fiches produits » : l'agent catalogue de l'orchestrateur.
 *
 *  - contrôle (lecture seule) : combien de fiches sont incomplètes ou incohérentes, et pourquoi ;
 *  - propositions de descriptions et de catégories par IA (CatalogEnricher) ;
 *  - propositions de prix à partir du prix d'achat et d'une marge que l'administrateur indique,
 *    chiffrées avec le même moteur que l'écran Révision des prix (BulkSalePriceUpdater) ;
 *  - liste des produits sans photo (le dépôt d'une photo dans la conversation la rattache).
 *
 * Garde-fous : rien n'est modifié à la demande. Une proposition est enregistrée dans un événement du
 * journal des agents ; elle n'est appliquée qu'au clic de l'administrateur, et seulement sur ce qui manque
 * encore à la fiche (jamais une description ou une catégorie déjà saisie). Une révision de prix refait son
 * chiffrage au moment d'appliquer et s'arrête si le lot n'est plus celui qui a été montré.
 */
class CatalogAssistant
{
    private const SHOWN = 10;

    public function __construct(
        private CatalogAudit $audit,
        private CatalogEnricher $enricher,
        private BulkSalePriceUpdater $prices,
    ) {
    }

    // ── Demandes ─────────────────────────────────────────────────────

    public function audit(User $admin): array
    {
        $r = $this->audit->run();
        $count = fn (string $check) => count($r['issues'][$check]);

        $this->record('catalogue_controle', AgentEvent::STATUS_DONE, 'Contrôle des fiches produits', [
            'counts' => array_map('count', $r['issues']), 'total' => $r['total'], 'flagged' => count($r['flagged']), 'requested_by' => $admin->name,
        ]);

        if ($r['total'] === 0) {
            return $this->reply("Le catalogue est vide : il n'y a aucune fiche produit à contrôler.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $lines = [];
        foreach (CatalogAudit::CHECKS as $check => $label) {
            $lines[] = "• {$label} : {$count($check)}";
        }
        $flagged = count($r['flagged']);
        $body = "Contrôle des fiches produits — {$r['total']} fiche(s)" . ($r['inactive'] > 0 ? " dont {$r['inactive']} inactive(s)" : '') . " :\n\n"
            . implode("\n", $lines) . "\n\n"
            . ($flagged === 0
                ? 'Toutes les fiches sont complètes et cohérentes.'
                : "{$flagged} fiche(s) à corriger. Je prépare des propositions, vous validez : rien n'est modifié sans votre clic.");

        $suggestions = [];
        $needsText = count(array_unique(array_merge($r['issues']['no_description'], $r['issues']['no_category'])));
        if ($needsText > 0 && $this->enricher->enabled()) {
            $suggestions[] = ['label' => 'Compléter descriptions et catégories (IA)', 'text' => 'complète les descriptions et catégories des fiches produits'];
        } elseif ($needsText > 0) {
            $body .= "\n\nPour que je propose descriptions et catégories, activez la compréhension avancée (IA) sur cet écran.";
        }
        if ($count('no_photo') > 0) {
            $suggestions[] = ['label' => 'Voir les produits sans photo', 'text' => 'quels produits sont sans photo'];
        }
        if ($count('no_sale_price') + $count('below_cost') > 0) {
            $suggestions[] = ['label' => 'Proposer des prix (25 % sur le prix d\'achat)', 'text' => 'révise les prix des fiches produits avec une marge de 25 %'];
        }

        return $this->reply($body, links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Révision des prix', 'to' => '/settings/bulk-prices']], suggestions: $suggestions);
    }

    public function photos(): array
    {
        $ids = $this->audit->query('no_photo')->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return $this->reply('Tous les produits ont au moins une photo.');
        }

        $products = Product::whereIn('id', array_slice($ids, 0, 15))->get(['id', 'p_title', 'p_sku']);
        $jadever = $this->audit->query('no_photo')->where('p_sku', 'like', 'JD%')->count();
        $body = count($ids) . " produit(s) sans photo. Les premiers :\n\n"
            . $products->map(fn (Product $p) => "• {$p->p_title} ({$p->p_sku})")->implode("\n")
            . (count($ids) > 15 ? "\n… et " . (count($ids) - 15) . ' autre(s).' : '')
            . "\n\nDéposez une photo ici (trombone ou glisser-déposer) : je la lis, je propose le produit correspondant et je la rattache après votre clic."
            . ($jadever > 0 ? "\nPour les {$jadever} produit(s) Jadever, les photos officielles se trouvent sur jadevermall.com/ma (recherche par référence) : je ne peux pas les télécharger moi-même, le site ne le permet pas." : '');

        return $this->reply($body, links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Galerie images', 'to' => '/storage/gallery']]);
    }

    public function complete(User $admin): array
    {
        $ids = array_values(array_unique(array_merge(
            $this->audit->query('no_description')->orderBy('id')->pluck('id')->all(),
            $this->audit->query('no_category')->orderBy('id')->pluck('id')->all(),
        )));
        if ($ids === []) {
            return $this->reply('Aucune fiche ne manque de description ni de catégorie.');
        }

        $batch = array_slice($ids, 0, CatalogEnricher::BATCH);
        $proposals = $this->enricher->propose($batch);
        if ($proposals === null) {
            return $this->reply('Je ne peux pas proposer de descriptions pour le moment : ' . ($this->enricher->failure() ?? 'lecture impossible') . '.', error: true);
        }
        if ($proposals === []) {
            return $this->reply("Je n'ai pas pu proposer de description ou de catégorie fiable pour ces " . count($batch) . " fiche(s) : leurs titres ne suffisent pas. Complétez-les dans l'écran Produits.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $event = $this->record('catalogue_completion', AgentEvent::STATUS_ROUTED, 'Propositions de descriptions et catégories', [
            'proposals' => $proposals, 'requested_by' => $admin->name,
        ]);

        $lines = array_map(function (array $p) {
            $parts = [];
            $p['description'] !== null && $parts[] = '« ' . mb_strimwidth($p['description'], 0, 140, '…') . ' »';
            $p['category'] !== null && $parts[] = "catégorie {$p['category']}" . (($p['new_category'] ?? null) !== null ? ' (à créer)' : '');

            return "• {$p['title']} : " . implode(' · ', $parts);
        }, $proposals);
        $rest = count($ids) - count($batch);
        $toCreate = array_values(array_unique(array_filter(array_column($proposals, 'new_category'))));

        return $this->reply(
            'Propositions pour ' . count($proposals) . " fiche(s) (lot #{$event->id}) :\n\n" . implode("\n", $lines)
            . ($toCreate ? "\n\nCatégories qui seraient créées : " . implode(', ', $toCreate) . '.' : '')
            . "\n\nÀ l'application, seul ce qui manque encore est rempli : une description ou une vraie catégorie déjà saisie n'est jamais écrasée."
            . ($rest > 0 ? "\n{$rest} autre(s) fiche(s) restent à traiter : redemandez après avoir appliqué ce lot." : ''),
            links: [['label' => 'Produits', 'to' => '/products']],
            suggestions: [
                ['label' => 'Appliquer ces propositions', 'text' => "applique les propositions du lot #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    /** @param string $n phrase normalisée (sans accent, minuscules) */
    public function pricing(User $admin, string $n): array
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $n, $m)) {
            return $this->reply(
                "Pour proposer des prix, dites-moi la marge : par exemple « révise les prix des fiches produits avec une marge de 25 % ». Elle s'ajoute au prix d'achat (ou, avec « marge sur le prix de vente », elle se calcule sur le prix de vente).",
                suggestions: array_map(fn (int $v) => ['label' => "Marge de {$v} %", 'text' => "révise les prix des fiches produits avec une marge de {$v} %"], [20, 25, 30]),
            );
        }
        $value = (float) str_replace(',', '.', $m[1]);
        $onSale = (bool) preg_match('/sur (le )?prix de vente|marge cible|marge visee/', $n);
        if ($value <= 0 || $value >= ($onSale ? BulkSalePriceUpdater::MAX_TARGET_MARGIN : 300)) {
            return $this->reply('Cette marge n\'est pas utilisable' . ($onSale ? ' : sur le prix de vente elle doit rester sous 100 %.' : ' : indiquez un pourcentage entre 0 et 300 %.'), error: true);
        }

        $r = $this->audit->run();
        $ids = array_values(array_unique(array_merge($r['issues']['no_sale_price'], $r['issues']['below_cost'])));
        if ($ids === []) {
            return $this->reply('Aucune fiche n\'a de prix de vente absent ou inférieur au prix d\'achat : il n\'y a rien à réviser.');
        }

        $filters = ['product_ids' => $ids, 'category_ids' => [], 'brand_ids' => [], 'status' => 'all', 'search' => null, 'margin_min' => null, 'margin_max' => null];
        $rule = $onSale
            ? ['mode' => 'target_margin', 'value' => $value, 'basis' => 'purchase', 'rounding' => BulkSalePriceUpdater::DEFAULT_ROUNDING]
            : ['mode' => 'percent', 'value' => $value, 'basis' => 'purchase', 'rounding' => BulkSalePriceUpdater::DEFAULT_ROUNDING];

        $preview = $this->prices->preview($filters, $rule, true);
        // Comme l'écran Révision des prix, le moteur ne traite que les articles en stock.
        $noStock = count($ids) - $preview['matched'];
        $noStockNote = $noStock > 0 ? "\n\n{$noStock} autre(s) fiche(s) à corriger n'ont pas de stock : la révision des prix ne traite que les articles en stock (comme l'écran Révision des prix), elles ne sont pas incluses." : '';
        if ($preview['changed'] === 0) {
            return $this->reply("Avec {$value} % " . ($onSale ? 'de marge sur le prix de vente' : "ajoutés au prix d'achat") . ', aucun prix ne change'
                . ($preview['skipped_no_basis'] > 0 ? " ({$preview['skipped_no_basis']} fiche(s) sans prix d'achat sont ignorées)" : '') . '.' . $noStockNote);
        }

        $event = $this->record('catalogue_prix', AgentEvent::STATUS_ROUTED, 'Proposition de révision des prix', [
            'filters' => $filters, 'rule' => $rule, 'expected_count' => $preview['matched'], 'requested_by' => $admin->name,
        ]);

        $lines = collect($preview['sample'])->take(self::SHOWN)->map(fn (array $s) => "• {$s['p_title']} : " . $this->money($s['current']) . ' → ' . $this->money($s['new']))->all();
        $body = "Révision des prix (lot #{$event->id}) : {$preview['matched']} fiche(s) sans prix ou vendues sous leur prix d'achat, avec "
            . ($onSale ? "{$value} % de marge sur le prix de vente" : "{$value} % ajoutés au prix d'achat") . " (arrondi à la dizaine de dirhams, comme l'écran Révision des prix)."
            . "\n{$preview['changed']} prix changent" . ($preview['skipped_no_basis'] > 0 ? ", {$preview['skipped_no_basis']} fiche(s) sans prix d'achat sont ignorées" : '') . " :\n\n"
            . implode("\n", $lines)
            . ($preview['changed'] > count($lines) ? "\n… et " . ($preview['changed'] - count($lines)) . ' autre(s).' : '')
            . ($preview['negative'] > 0 ? "\n\nAttention : {$preview['negative']} prix seraient négatifs, l'application sera refusée." : '')
            . ($preview['below_purchase'] ? "\n\nAttention : {$preview['below_purchase']} prix resteraient sous le prix d'achat." : '')
            . $noStockNote;

        return $this->reply(
            $body,
            links: [['label' => 'Révision des prix', 'to' => '/settings/bulk-prices']],
            suggestions: [
                ['label' => 'Appliquer cette révision', 'text' => "applique la révision des prix du lot #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    // ── Actions confirmées par l'administrateur ──────────────────────

    /** « applique les propositions du lot #12 », « ignore le lot #12 ». @return array{body: string, meta: array<string, mixed>} */
    public function act(User $admin, int $eventId, string $n): array
    {
        $event = AgentEvent::whereIn('type', ['catalogue_completion', 'catalogue_prix'])->find($eventId);
        if (!$event) {
            return $this->reply("Je ne trouve pas le lot #{$eventId}.", error: true);
        }
        if ($event->status !== AgentEvent::STATUS_ROUTED) {
            return $this->reply("Le lot #{$eventId} a déjà été traité ou ignoré.", eventId: $eventId);
        }
        if (preg_match('/ignor|annul|abandon/', $n)) {
            $event->update(['status' => AgentEvent::STATUS_REJECTED, 'payload' => array_merge($event->payload ?? [], ['dismissed_by' => $admin->name])]);

            return $this->reply("C'est noté : le lot #{$eventId} est ignoré, aucune fiche n'a été modifiée.", eventId: $eventId);
        }
        if (!preg_match('/appliqu|confirm|valid|lance/', $n)) {
            return $this->reply("Lot #{$eventId} en attente : dites « applique … du lot #{$eventId} » ou « ignore le lot #{$eventId} ».", eventId: $eventId);
        }

        try {
            return $event->type === 'catalogue_completion' ? $this->applyCompletion($event) : $this->applyPrices($event);
        } catch (\Throwable $e) {
            Log::error("Lot #{$eventId} : application échouée : {$e->getMessage()}");

            return $this->reply("L'application du lot #{$eventId} a échoué : vérifiez les fiches dans l'écran Produits.", error: true, eventId: $eventId);
        }
    }

    private function applyCompletion(AgentEvent $event): array
    {
        $descriptions = $categories = $created = 0;
        $byName = [];   // une catégorie à créer n'est créée qu'une fois, même proposée pour plusieurs fiches

        DB::transaction(function () use ($event, &$descriptions, &$categories, &$created, &$byName) {
            foreach ($event->payload['proposals'] ?? [] as $p) {
                $product = Product::find($p['product_id'] ?? 0);
                if (!$product) {
                    continue;
                }
                $changed = false;
                // Seul ce qui manque encore : une saisie faite depuis la proposition n'est jamais écrasée.
                if (($p['description'] ?? null) !== null && $this->enricher->needsDescription($product)) {
                    $product->p_description = $p['description'];
                    $descriptions++;
                    $changed = true;
                }
                if ($this->enricher->needsCategory($product)) {
                    $categoryId = $p['category_id'] ?? null;
                    if ($categoryId !== null && !Category::whereKey($categoryId)->exists()) {
                        $categoryId = null;
                    }
                    if ($categoryId === null && ($p['new_category'] ?? null) !== null) {
                        $key = $this->enricher->fold($p['new_category']);
                        $categoryId = $byName[$key] ?? Category::all(['id', 'ctg_title'])->first(fn (Category $c) => $this->enricher->fold($c->ctg_title) === $key)?->id;
                        if ($categoryId === null) {
                            $categoryId = Category::create(['ctg_title' => $p['new_category'], 'ctg_status' => true])->id;
                            $created++;
                        }
                        $byName[$key] = $categoryId;
                    }
                    if ($categoryId !== null) {
                        $product->category_id = $categoryId;
                        $categories++;
                        $changed = true;
                    }
                }
                $changed && $product->save();
            }
        });
        $created > 0 && CacheService::flushCategories();

        $result = ['descriptions' => $descriptions, 'categories' => $categories, 'categories_created' => $created];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['result' => $result])]);
        $this->log($event, 'catalog_completion_applied', $result);

        return $this->reply("Lot #{$event->id} appliqué : {$descriptions} description(s) et {$categories} catégorie(s) renseignées" . ($created > 0 ? " ({$created} catégorie(s) créée(s))" : '') . ". Ce qui était déjà saisi n'a pas été touché.", links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Catégories', 'to' => '/categories']], eventId: $event->id);
    }

    private function applyPrices(AgentEvent $event): array
    {
        $filters = $event->payload['filters'] ?? [];
        $rule = $event->payload['rule'] ?? [];
        $expected = (int) ($event->payload['expected_count'] ?? 0);

        // Même garde que l'écran : on rechiffre, et on s'arrête si le lot n'est plus celui qui a été montré.
        $preview = $this->prices->preview($filters, $rule, true);
        if ($preview['matched'] !== $expected || $preview['negative'] > 0 || $preview['matched'] > BulkSalePriceUpdater::MAX_PRODUCTS) {
            return $this->reply("Le lot #{$event->id} n'a pas été appliqué : le périmètre a changé depuis le chiffrage ({$preview['matched']} fiche(s) au lieu de {$expected})"
                . ($preview['negative'] > 0 ? ' ou un prix serait négatif' : '') . '. Redemandez la révision.', error: true, eventId: $event->id);
        }

        $updated = $this->prices->apply($filters, $rule);
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['result' => ['updated' => $updated]])]);
        $this->log($event, 'catalog_prices_applied', ['updated' => $updated, 'rule' => $rule]);

        return $this->reply("Lot #{$event->id} appliqué : {$updated} prix de vente mis à jour. Les changements sont dans la piste d'audit.", links: [['label' => 'Produits', 'to' => '/products']], eventId: $event->id);
    }

    // ── Outils ───────────────────────────────────────────────────────

    private function record(string $type, string $status, string $text, array $payload): AgentEvent
    {
        return AgentEvent::create([
            'type'     => $type,
            'source'   => 'orchestrator',
            'status'   => $status,
            'agent_id' => Agent::where('domain', 'achats')->value('id'),
            'payload'  => array_merge(['text' => $text], $payload),
        ]);
    }

    private function log(AgentEvent $event, string $action, array $result): void
    {
        AgentAction::create([
            'agent_id' => $event->agent_id ?? Agent::where('domain', 'achats')->value('id'),
            'event_id' => $event->id,
            'action'   => $action,
            'level'    => 'approval',
            'input'    => ['requested_by' => $event->payload['requested_by'] ?? null],
            'result'   => $result,
        ]);
    }

    private function money(?float $amount): string
    {
        return $amount === null ? '—' : number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, to: string}> $links
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'      => 'catalog',
            'links'       => $links ?: null,
            'error'       => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
        ], fn ($v) => $v !== null)];
    }
}
