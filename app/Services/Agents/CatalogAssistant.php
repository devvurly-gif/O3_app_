<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Brand;
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
    private const ACTIVATION_BATCH = 100;
    private const BARCODE_BATCH = 200;

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
        $needsText = count($this->toComplete());
        if ($flagged > 0) {
            $suggestions[] = ['label' => 'Préparer les fiches pour l\'utilisation', 'text' => "prépare les fiches pour l'utilisation"];
        }
        if ($needsText > 0 && $this->enricher->enabled()) {
            $suggestions[] = ['label' => 'Compléter descriptions, catégories et marques (IA)', 'text' => 'complète les descriptions, catégories et marques des fiches produits'];
        } elseif ($needsText > 0) {
            $body .= "\n\nPour que je propose descriptions, catégories et marques, activez la compréhension avancée (IA) sur cet écran.";
        }
        if ($count('no_barcode') > 0) {
            $suggestions[] = ['label' => 'Attribuer des codes-barres', 'text' => 'attribue des codes-barres aux fiches produits'];
        }
        if ($count('no_photo') > 0) {
            $suggestions[] = ['label' => 'Voir les produits sans photo', 'text' => 'quels produits sont sans photo'];
        }
        if ($count('no_sale_price') + $count('below_cost') > 0) {
            $suggestions[] = ['label' => 'Proposer des prix (25 % sur le prix d\'achat)', 'text' => 'révise les prix des fiches produits avec une marge de 25 %'];
        }

        if ($r['inactive'] > 0) {
            $suggestions[] = ['label' => "Préparer l'activation des fiches", 'text' => 'active les fiches produits'];
        }

        return $this->reply($body, links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Révision des prix', 'to' => '/settings/bulk-prices']], suggestions: $suggestions);
    }

    public function photos(): array
    {
        // File d'attente par priorité : d'abord les articles qui ont du stock (ceux qu'on vend), puis le reste.
        $ids = $this->audit->query('no_photo')->withSum('warehouseStocks as stock_qty', 'stockLevel')
            ->orderByDesc('stock_qty')->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return $this->reply('Tous les produits ont au moins une photo.');
        }

        $firstIds = array_slice($ids, 0, 15);
        $products = Product::whereIn('id', $firstIds)->get(['id', 'p_title', 'p_sku'])->sortBy(fn (Product $p) => array_search($p->id, $firstIds, true));
        $jadever = $this->audit->query('no_photo')->where('p_sku', 'like', 'JD%')->count();
        $body = count($ids) . " produit(s) sans photo, par priorité (articles en stock d'abord). Les premiers :\n\n"
            . $products->map(fn (Product $p) => "• {$p->p_title} ({$p->p_sku})")->implode("\n")
            . (count($ids) > 15 ? "\n… et " . (count($ids) - 15) . ' autre(s).' : '')
            . "\n\nDéposez une photo ici (trombone ou glisser-déposer) : je la lis, je propose le produit correspondant et je la rattache après votre clic."
            . ($jadever > 0 ? "\nPour les {$jadever} produit(s) Jadever, les photos officielles se trouvent sur jadevermall.com/ma (recherche par référence) : je ne peux pas les télécharger moi-même, le site ne le permet pas." : '');

        return $this->reply($body, links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Galerie images', 'to' => '/storage/gallery']]);
    }

    public function complete(User $admin): array
    {
        $ids = $this->toComplete();
        if ($ids === []) {
            return $this->reply('Aucune fiche ne manque de description, de catégorie ni de marque.');
        }

        $batch = array_slice($ids, 0, CatalogEnricher::BATCH);
        $proposals = $this->enricher->propose($batch);
        if ($proposals === null) {
            return $this->reply('Je ne peux pas proposer de descriptions pour le moment : ' . ($this->enricher->failure() ?? 'lecture impossible') . '.', error: true);
        }
        if ($proposals === []) {
            return $this->reply("Je n'ai pas pu proposer de description ou de catégorie fiable pour ces " . count($batch) . " fiche(s) : leurs titres ne suffisent pas. Complétez-les dans l'écran Produits.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $event = $this->record('catalogue_completion', AgentEvent::STATUS_ROUTED, 'Propositions de descriptions, catégories et marques', [
            'proposals' => $proposals, 'requested_by' => $admin->name,
        ]);

        $lines = array_map(function (array $p) {
            $parts = [];
            $p['description'] !== null && $parts[] = '« ' . mb_strimwidth($p['description'], 0, 140, '…') . ' »';
            $p['category'] !== null && $parts[] = "catégorie {$p['category']}" . (($p['new_category'] ?? null) !== null ? ' (à créer)' : '');
            ($p['brand'] ?? null) !== null && $parts[] = "marque {$p['brand']}" . (($p['new_brand'] ?? null) !== null ? ' (à créer)' : '');
            ($p['long_description'] ?? null) !== null && $parts[] = 'description longue (' . mb_strlen($p['long_description']) . ' caractères)';

            return "• {$p['title']} : " . implode(' · ', $parts);
        }, $proposals);
        $rest = count($ids) - count($batch);
        $toCreate = array_values(array_unique(array_filter(array_column($proposals, 'new_category'))));
        $brandsToCreate = array_values(array_unique(array_filter(array_column($proposals, 'new_brand'))));

        return $this->reply(
            'Propositions pour ' . count($proposals) . " fiche(s) (lot #{$event->id}) :\n\n" . implode("\n", $lines)
            . ($toCreate ? "\n\nCatégories qui seraient créées : " . implode(', ', $toCreate) . '.' : '')
            . ($brandsToCreate ? "\n\nMarques qui seraient créées : " . implode(', ', $brandsToCreate) . '.' : '')
            . "\n\nÀ l'application, seul ce qui manque encore est rempli : une description, une vraie catégorie ou une vraie marque déjà saisie n'est jamais écrasée."
            . ($rest > 0 ? "\n{$rest} autre(s) fiche(s) restent à traiter : redemandez après avoir appliqué ce lot." : ''),
            links: [['label' => 'Produits', 'to' => '/products']],
            suggestions: array_values(array_filter([
                ['label' => 'Appliquer ces propositions', 'text' => "applique les propositions du lot #{$event->id}"],
                // Un clic par lot : applique celui-ci puis prépare le suivant (qui reste à valider).
                $rest > 0 ? ['label' => 'Appliquer et préparer le suivant', 'text' => "applique les propositions du lot #{$event->id} et prépare le lot suivant"] : null,
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ])),
            eventId: $event->id,
        );
    }

    /**
     * Propose d'activer les fiches inactives qui sont prêtes : vraie catégorie, prix de vente et description.
     * Une fiche sans photo reste activable (signalé). L'activation ne change que le statut de la fiche.
     */
    public function activation(User $admin): array
    {
        $inactive = Product::where('p_status', false)->get();
        if ($inactive->isEmpty()) {
            return $this->reply('Aucune fiche inactive : toutes les fiches sont déjà actives.');
        }

        $ready = $missing = [];
        foreach ($inactive as $p) {
            $why = $this->notReadyBecause($p);
            $why === [] ? $ready[] = $p : $missing[$p->id] = $why;
        }
        if ($ready === []) {
            return $this->reply("{$inactive->count()} fiche(s) inactive(s), mais aucune n'est prête : il manque une vraie catégorie, un prix de vente ou une description. Lancez d'abord le contrôle (« mettre à jour les fiches produits »).", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $ids = array_map(fn (Product $p) => $p->id, array_slice($ready, 0, self::ACTIVATION_BATCH));
        $noPhoto = Product::whereIn('id', $ids)->whereDoesntHave('images')->count();
        $ecom = Product::whereIn('id', $ids)->where('is_ecom', true)->count();

        $event = $this->record('catalogue_activation', AgentEvent::STATUS_ROUTED, "Proposition d'activation de " . count($ids) . ' fiche(s)', [
            'product_ids' => $ids, 'requested_by' => $admin->name,
        ]);

        $body = "{$inactive->count()} fiche(s) inactive(s) : " . count($ready) . ' prête(s) (vraie catégorie, prix de vente, description)'
            . ($missing ? ', ' . count($missing) . ' incomplète(s) laissée(s) inactive(s)' : '') . ".\n\n"
            . 'Lot #' . $event->id . ' : activer ' . count($ids) . ' fiche(s)'
            . (count($ready) > count($ids) ? ' (les ' . self::ACTIVATION_BATCH . ' premières, redemandez pour la suite)' : '') . '.'
            . ($noPhoto > 0 ? "\n• {$noPhoto} sans photo : activables quand même, les photos peuvent venir après." : '')
            . "\n• " . ($ecom > 0 ? "{$ecom} marquée(s) « boutique en ligne » : elles y seraient publiées." : "Aucune n'est marquée « boutique en ligne » : rien ne sera publié sur la boutique.")
            . "\n• L'activation ne change que le statut de la fiche (inscrit à la piste d'audit). Pour revenir en arrière, désactivez les fiches dans l'écran Produits.";

        return $this->reply(
            $body,
            links: [['label' => 'Produits', 'to' => '/products']],
            suggestions: [
                ['label' => 'Activer ces ' . count($ids) . ' fiches', 'text' => "applique l'activation des fiches du lot #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    /** @return array<int, string> ce qui manque à la fiche pour être activée ; vide si elle est prête */
    private function notReadyBecause(Product $p): array
    {
        $why = [];
        $this->enricher->needsCategory($p) && $why[] = 'catégorie';
        ((float) $p->p_salePrice) <= 0 && $why[] = 'prix de vente';
        $this->enricher->needsDescription($p) && $why[] = 'description';

        return $why;
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
        $event = AgentEvent::whereIn('type', ['catalogue_completion', 'catalogue_prix', 'catalogue_activation', 'catalogue_codes_barres'])->find($eventId);
        if (!$event) {
            return $this->reply("Je ne trouve pas le lot #{$eventId}.", error: true);
        }
        if ($event->status !== AgentEvent::STATUS_ROUTED) {
            return $this->reply("Le lot #{$eventId} a déjà été traité ou ignoré.", eventId: $eventId);
        }
        if (preg_match('/ignor|annul|abandon/', $n)) {
            $event->update(['status' => AgentEvent::STATUS_REJECTED, 'payload' => array_merge($event->payload ?? [], ['dismissed_by' => $admin->name])]);

            return $this->withNextStep($this->reply("C'est noté : le lot #{$eventId} est ignoré, aucune fiche n'a été modifiée.", eventId: $eventId));
        }
        if (!preg_match('/appliqu|confirm|valid|lance/', $n)) {
            return $this->reply("Lot #{$eventId} en attente : dites « applique … du lot #{$eventId} » ou « ignore le lot #{$eventId} ».", eventId: $eventId);
        }

        try {
            if ($event->type !== 'catalogue_completion') {
                return $this->withNextStep(match ($event->type) {
                    'catalogue_activation'   => $this->applyActivation($event),
                    'catalogue_codes_barres' => $this->applyBarcodes($event),
                    default                  => $this->applyPrices($event),
                });
            }

            $applied = $this->applyCompletion($event);
            if (!preg_match('/suivant/', $n) || ($applied['meta']['error'] ?? false)) {
                return $this->withNextStep($applied);
            }

            // « … et prépare le lot suivant » : le lot appliqué, puis la proposition suivante — toujours à valider.
            $next = $this->complete($admin);

            return ['body' => $applied['body'] . "\n\n— Lot suivant —\n" . $next['body'], 'meta' => array_filter([
                'intent'      => 'catalog',
                'links'       => array_values(collect(array_merge($applied['meta']['links'] ?? [], $next['meta']['links'] ?? []))->unique('to')->all()) ?: null,
                'error'       => $next['meta']['error'] ?? null,
                'event_id'    => $next['meta']['event_id'] ?? $eventId,
                'suggestions' => $next['meta']['suggestions'] ?? null,
            ], fn ($v) => $v !== null)];
        } catch (\Throwable $e) {
            Log::error("Lot #{$eventId} : application échouée : {$e->getMessage()}");

            return $this->reply("L'application du lot #{$eventId} a échoué : vérifiez les fiches dans l'écran Produits.", error: true, eventId: $eventId);
        }
    }

    /** Après une application : s'il reste des fiches à préparer, propose l'étape suivante du parcours. */
    private function withNextStep(array $reply): array
    {
        $text = "prépare les fiches pour l'utilisation";
        $suggestions = $reply['meta']['suggestions'] ?? [];
        if (($reply['meta']['error'] ?? false) || in_array($text, array_column($suggestions, 'text'), true) || count($this->audit->run()['flagged']) === 0) {
            return $reply;
        }
        $suggestions[] = ['label' => 'Étape suivante', 'text' => $text];
        $reply['meta']['suggestions'] = $suggestions;

        return $reply;
    }

    private function applyCompletion(AgentEvent $event): array
    {
        $descriptions = $long = $categories = $brands = $createdCategories = $createdBrands = 0;
        $categoryByName = $brandByName = [];   // une catégorie ou marque à créer n'est créée qu'une fois, même proposée pour plusieurs fiches

        DB::transaction(function () use ($event, &$descriptions, &$long, &$categories, &$brands, &$createdCategories, &$createdBrands, &$categoryByName, &$brandByName) {
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
                if (($p['long_description'] ?? null) !== null && $this->enricher->needsLongDescription($product)) {
                    $product->p_long_description = $p['long_description'];
                    $long++;
                    $changed = true;
                }
                if ($this->enricher->needsCategory($product)) {
                    $categoryId = $this->resolveCategory($p, $categoryByName, $createdCategories);
                    if ($categoryId !== null) {
                        $product->category_id = $categoryId;
                        $categories++;
                        $changed = true;
                    }
                }
                if ($this->enricher->needsBrand($product)) {
                    $brandId = $this->resolveBrand($p, $brandByName, $createdBrands);
                    if ($brandId !== null) {
                        $product->brand_id = $brandId;
                        $brands++;
                        $changed = true;
                    }
                }
                $changed && $product->save();
            }
        });
        $createdCategories > 0 && CacheService::flushCategories();
        $createdBrands > 0 && CacheService::flushBrands();

        $result = [
            'descriptions' => $descriptions, 'long_descriptions' => $long, 'categories' => $categories, 'categories_created' => $createdCategories,
            'brands' => $brands, 'brands_created' => $createdBrands,
        ];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['result' => $result])]);
        $this->log($event, 'catalog_completion_applied', $result);

        $parts = ["{$descriptions} description(s)"];
        $long > 0 && $parts[] = "{$long} description(s) longue(s)";
        $parts[] = "{$categories} catégorie(s)" . ($createdCategories > 0 ? " ({$createdCategories} créée(s))" : '');
        $parts[] = "{$brands} marque(s)" . ($createdBrands > 0 ? " ({$createdBrands} créée(s))" : '');

        return $this->reply(
            "Lot #{$event->id} appliqué : " . implode(', ', $parts) . " renseignée(s). Ce qui était déjà saisi n'a pas été touché.",
            links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Catégories', 'to' => '/categories'], ['label' => 'Marques', 'to' => '/brands']],
            eventId: $event->id,
            suggestions: $this->remainingToComplete() > 0 && $this->enricher->enabled()
                ? [['label' => 'Préparer le lot suivant', 'text' => 'complète les descriptions, catégories et marques des fiches produits']]
                : [],
        );
    }

    /** La catégorie à donner : celle proposée si elle existe encore, sinon la nouvelle (créée une seule fois, ou retrouvée si elle existe déjà). */
    private function resolveCategory(array $p, array &$byName, int &$created): ?int
    {
        $id = $p['category_id'] ?? null;
        if ($id !== null && Category::whereKey($id)->exists()) {
            return (int) $id;
        }
        if (($p['new_category'] ?? null) === null) {
            return null;
        }
        $key = $this->enricher->fold($p['new_category']);
        $found = $byName[$key] ?? Category::all(['id', 'ctg_title'])->first(fn (Category $c) => $this->enricher->fold($c->ctg_title) === $key)?->id;
        if ($found === null) {
            $found = Category::create(['ctg_title' => $p['new_category'], 'ctg_status' => true])->id;
            $created++;
        }

        return $byName[$key] = (int) $found;
    }

    private function resolveBrand(array $p, array &$byName, int &$created): ?int
    {
        $id = $p['brand_id'] ?? null;
        if ($id !== null && Brand::whereKey($id)->exists()) {
            return (int) $id;
        }
        if (($p['new_brand'] ?? null) === null) {
            return null;
        }
        $key = $this->enricher->fold($p['new_brand']);
        $found = $byName[$key] ?? Brand::all(['id', 'br_title'])->first(fn (Brand $b) => $this->enricher->fold($b->br_title) === $key)?->id;
        if ($found === null) {
            $found = Brand::create(['br_title' => $p['new_brand'], 'br_status' => true])->id;
            $created++;
        }

        return $byName[$key] = (int) $found;
    }

    /** Les fiches qui attendent une description, une vraie catégorie, une vraie marque ou (boutique en ligne) une description longue. @return array<int, int> */
    private function toComplete(): array
    {
        return array_values(array_unique(array_merge(
            $this->audit->query('no_description')->orderBy('id')->pluck('id')->all(),
            $this->audit->query('no_category')->orderBy('id')->pluck('id')->all(),
            $this->audit->query('no_brand')->orderBy('id')->pluck('id')->all(),
            $this->audit->query('no_long_description')->orderBy('id')->pluck('id')->all(),
        )));
    }

    private function applyActivation(AgentEvent $event): array
    {
        $activated = $skipped = 0;
        DB::transaction(function () use ($event, &$activated, &$skipped) {
            foreach (Product::whereIn('id', $event->payload['product_ids'] ?? [])->get() as $product) {
                // Une fiche déjà active, ou redevenue incomplète depuis la proposition, n'est pas activée.
                if ($product->p_status || $this->notReadyBecause($product) !== []) {
                    $skipped++;
                    continue;
                }
                $product->p_status = true;
                $product->save();
                $activated++;
            }
        });

        $result = ['activated' => $activated, 'skipped' => $skipped];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['result' => $result])]);
        $this->log($event, 'catalog_activation_applied', $result);

        return $this->reply("Lot #{$event->id} appliqué : {$activated} fiche(s) activée(s)" . ($skipped > 0 ? ", {$skipped} laissée(s) inactive(s) (déjà actives ou devenues incomplètes depuis la proposition)" : '') . '.', links: [['label' => 'Produits', 'to' => '/products']], eventId: $event->id);
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

    // ── Codes-barres, parcours « fiches prêtes » ─────────────────────

    /**
     * Propose un code-barres INTERNE (préfixe 29, voir InternalEan13) aux fiches qui n'en ont pas. Jamais un code
     * de fournisseur ou de fabricant : l'IA n'intervient pas, le code est calculé.
     */
    public function barcodes(User $admin): array
    {
        $ids = $this->audit->query('no_barcode')->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return $this->reply('Toutes les fiches ont un code-barres.');
        }

        $batch = array_slice($ids, 0, self::BARCODE_BATCH);
        $products = Product::whereIn('id', $batch)->orderBy('id')->get(['id', 'p_title', 'p_sku']);
        $assigned = [];
        foreach ($products as $p) {
            $code = InternalEan13::forProduct($p->id);
            if (!Product::where('p_ean13', $code)->exists()) {
                $assigned[$p->id] = $code;
            }
        }
        if ($assigned === []) {
            return $this->reply("Je n'ai pu calculer aucun code libre pour ces fiches : saisissez-les dans l'écran Produits.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $event = $this->record('catalogue_codes_barres', AgentEvent::STATUS_ROUTED, 'Proposition de codes-barres internes pour ' . count($assigned) . ' fiche(s)', [
            'codes' => $assigned, 'requested_by' => $admin->name,
        ]);

        $examples = $products->filter(fn (Product $p) => isset($assigned[$p->id]))->take(self::SHOWN)->map(fn (Product $p) => "• {$p->p_title} : {$assigned[$p->id]}")->implode("\n");
        $rest = count($ids) - count($assigned);

        return $this->reply(
            "Codes-barres pour " . count($assigned) . " fiche(s) (lot #{$event->id}) :\n\n{$examples}"
            . (count($assigned) > self::SHOWN ? "\n… et " . (count($assigned) - self::SHOWN) . ' autre(s).' : '')
            . "\n\nCe sont des codes INTERNES (préfixe 29, clé de contrôle valide) : ils servent à la caisse et aux étiquettes produits, ce ne sont pas des codes de fabricant. Une fiche qui a déjà un code n'est jamais modifiée."
            . ($rest > 0 ? "\n{$rest} autre(s) fiche(s) restent à traiter : redemandez après avoir appliqué ce lot." : ''),
            links: [['label' => 'Produits', 'to' => '/products'], ['label' => 'Étiquettes produits', 'to' => '/products/labels']],
            suggestions: [
                ['label' => 'Appliquer ces codes-barres', 'text' => "applique les codes-barres du lot #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    private function applyBarcodes(AgentEvent $event): array
    {
        $applied = $skipped = 0;
        DB::transaction(function () use ($event, &$applied, &$skipped) {
            foreach ($event->payload['codes'] ?? [] as $productId => $code) {
                $product = Product::find($productId);
                // Seulement si la fiche n'a toujours pas de code et que ce code est libre et valide.
                if (!$product || trim((string) $product->p_ean13) !== '' || !InternalEan13::isValid((string) $code) || Product::where('p_ean13', $code)->exists()) {
                    $skipped++;
                    continue;
                }
                $product->p_ean13 = $code;
                $product->save();
                $applied++;
            }
        });

        $result = ['applied' => $applied, 'skipped' => $skipped];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['result' => $result])]);
        $this->log($event, 'catalog_barcodes_applied', $result);

        return $this->reply("Lot #{$event->id} appliqué : {$applied} code(s)-barres attribué(s)" . ($skipped > 0 ? ", {$skipped} fiche(s) laissée(s) telles quelles (déjà un code, ou code devenu indisponible)" : '') . '.', links: [['label' => 'Étiquettes produits', 'to' => '/products/labels']], eventId: $event->id);
    }

    /**
     * Le parcours « fiches prêtes à l'emploi » : où en est le catalogue, et la prochaine étape à valider.
     * Ordre : descriptions, catégories et marques (IA) → prix → codes-barres → activation → photos.
     * Chaque étape reste une proposition à valider ; cette demande ne fait que choisir la première qui a du travail.
     */
    public function prepare(User $admin): array
    {
        $r = $this->audit->run();
        if ($r['total'] === 0) {
            return $this->reply("Le catalogue est vide : il n'y a aucune fiche à préparer.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $count = fn (string $check) => count($r['issues'][$check]);
        $inactiveReady = Product::where('p_status', false)->get()->filter(fn (Product $p) => $this->notReadyBecause($p) === [])->count();
        $needsText = count($this->toComplete());

        // Chaque étape : [libellé, travail restant, méthode]. Les étapes sans travail sont affichées « terminées ».
        $steps = [
            ['Descriptions, catégories et marques', $needsText, fn () => $this->enricher->enabled() ? $this->complete($admin) : $this->reply("Cette étape demande la compréhension avancée (IA) : activez-la sur cet écran, puis redemandez.", error: true)],
            ['Prix de vente', $count('no_sale_price') + $count('below_cost'), fn () => $this->pricing($admin, '')],
            ['Codes-barres', $count('no_barcode'), fn () => $this->barcodes($admin)],
            ['Activation des fiches prêtes', $inactiveReady, fn () => $this->activation($admin)],
            ['Photos', $count('no_photo'), fn () => $this->photos()],
        ];

        $lines = [];
        $todo = null;
        foreach ($steps as $i => [$label, $remaining, $run]) {
            $lines[] = ($remaining === 0 ? '✓ ' : '→ ') . 'Étape ' . ($i + 1) . ' — ' . $label . ($remaining === 0 ? ' : terminée' : " : {$remaining} fiche(s)");
            if ($remaining > 0 && $todo === null) {
                $todo = [$label, $run];
            }
        }

        $ready = $r['total'] - count($r['flagged']);
        $header = "Préparation des fiches pour l'utilisation — {$ready} fiche(s) prête(s) sur {$r['total']} (catégorie, marque, prix, description, photo et code-barres" . ($count('no_long_description') > 0 ? ', description longue' : '') . ").\n\n" . implode("\n", $lines);
        if ($todo === null) {
            return $this->reply($header . "\n\nToutes les étapes sont terminées : les fiches sont prêtes à l'emploi.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        // Les photos ne se proposent pas : on les liste (la dernière étape) puis on laisse l'administrateur déposer.
        $step = ($todo[1])();

        return ['body' => $header . "\n\n— Prochaine étape : {$todo[0]} —\n" . $step['body'], 'meta' => $step['meta']];
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** Combien de fiches attendent encore une description, une vraie catégorie ou une vraie marque. */
    private function remainingToComplete(): int
    {
        return count($this->toComplete());
    }

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
