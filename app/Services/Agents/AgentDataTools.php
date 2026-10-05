<?php

namespace App\Services\Agents;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Les outils de LECTURE qu'un agent recruté peut appeler. Ce sont les seules portes vers les données de
 * l'entreprise : le modèle ne rédige jamais de requête, il choisit un outil et ses paramètres bornés.
 *
 * Garde-fous : lecture seule (aucun outil n'écrit), résultats plafonnés (au plus 40 lignes), montants arrondis,
 * et chaque outil n'est offert que si le domaine de données correspondant a été accordé à l'agent (`scopes`).
 * Ce que l'outil renvoie est envoyé à Anthropic pour l'analyse : les domaines sensibles (noms de clients,
 * montants) sont annoncés à l'administrateur avant le recrutement.
 */
class AgentDataTools
{
    /** @var array<string, array{label: string, reads: string, sensitive: bool, tools: array<int, string>}> */
    public const SCOPES = [
        'stock'     => ['label' => 'Stock', 'reads' => 'quantités en stock par produit et par entrepôt (références et titres)', 'sensitive' => false, 'tools' => ['stock_bas']],
        'ventes'    => ['label' => 'Ventes', 'reads' => 'totaux des factures de vente et principaux clients (noms et montants)', 'sensitive' => true, 'tools' => ['ventes_resume']],
        'creances'  => ['label' => 'Créances clients', 'reads' => 'factures de vente impayées : noms de clients, références, échéances et montants dus', 'sensitive' => true, 'tools' => ['creances']],
        'achats'    => ['label' => 'Achats', 'reads' => "totaux des factures d'achat et principaux fournisseurs (noms et montants)", 'sensitive' => true, 'tools' => ['achats_resume']],
        'produits'  => ['label' => 'Produits', 'reads' => 'recherche de produits : titre, référence, prix de vente et stock total', 'sensitive' => false, 'tools' => ['produit_recherche']],
        'catalogue' => ['label' => 'Qualité du catalogue', 'reads' => 'nombre de fiches produits incomplètes, par type de manque', 'sensitive' => false, 'tools' => ['qualite_fiches']],
    ];

    /** @param array<int, string> $scopes @return array<int, string> les domaines valides, sans doublon */
    public static function sanitizeScopes(array $scopes): array
    {
        return array_values(array_unique(array_filter($scopes, fn ($s) => is_string($s) && isset(self::SCOPES[$s]))));
    }

    /** Les définitions d'outils (schéma Anthropic) offertes pour ces domaines. @param array<int, string> $scopes */
    public function definitions(array $scopes): array
    {
        $all = $this->allDefinitions();
        $names = [];
        foreach (self::sanitizeScopes($scopes) as $scope) {
            $names = array_merge($names, self::SCOPES[$scope]['tools']);
        }

        return array_values(array_intersect_key($all, array_flip($names)));
    }

    /** Exécute un outil ; un outil non offert à l'agent est refusé. @param array<int, string> $scopes */
    public function run(string $tool, array $input, array $scopes): array
    {
        $allowed = [];
        foreach (self::sanitizeScopes($scopes) as $scope) {
            $allowed = array_merge($allowed, self::SCOPES[$scope]['tools']);
        }
        if (!in_array($tool, $allowed, true)) {
            return ['erreur' => "Outil non autorisé pour cet agent : {$tool}."];
        }

        $handlers = [
            'stock_bas'         => fn () => $this->stockBas($input),
            'ventes_resume'     => fn () => $this->ventesResume($input),
            'creances'          => fn () => $this->creances(),
            'achats_resume'     => fn () => $this->achatsResume($input),
            'produit_recherche' => fn () => $this->produitRecherche($input),
            'qualite_fiches'    => fn () => $this->qualiteFiches(),
        ];

        // Tout outil autorisé a un gestionnaire (SCOPES et $handlers sont tenus ensemble, un test le vérifie).
        return $handlers[$tool]();
    }

    // ── Outils ───────────────────────────────────────────────────────

    private function stockBas(array $in): array
    {
        $threshold = max(0, min(100, (int) ($in['seuil'] ?? 5)));
        $warehouseId = null;
        if (is_string($in['entrepot'] ?? null) && $in['entrepot'] !== '') {
            $warehouseId = DB::table('warehouses')->whereRaw('LOWER(wh_title) = ?', [mb_strtolower(trim($in['entrepot']))])->value('id');
            if ($warehouseId === null) {
                return ['erreur' => "Entrepôt introuvable : {$in['entrepot']}."];
            }
        }

        $qty = DB::table('warehouse_has_stock')->selectRaw('product_id, COALESCE(SUM(stockLevel), 0) AS qty')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))->groupBy('product_id');
        $rows = DB::table('products as p')->leftJoinSub($qty, 's', 's.product_id', '=', 'p.id')->whereNull('p.deleted_at')
            ->selectRaw('p.p_sku AS reference, p.p_title AS titre, COALESCE(s.qty, 0) AS quantite')
            ->whereRaw('COALESCE(s.qty, 0) <= ?', [$threshold]);

        return [
            'seuil'             => $threshold,
            'produits_concernes' => (clone $rows)->count(),
            'en_rupture'        => (clone $rows)->whereRaw('COALESCE(s.qty, 0) <= 0')->count(),
            'plus_bas'          => $rows->orderByRaw('COALESCE(s.qty, 0)')->orderBy('p.id')->limit(40)->get()
                ->map(fn ($r) => ['reference' => $r->reference, 'titre' => $r->titre, 'quantite' => (float) $r->quantite])->all(),
        ];
    }

    private function ventesResume(array $in): array
    {
        return $this->invoiceSummary('InvoiceSale', $in);
    }

    private function achatsResume(array $in): array
    {
        return $this->invoiceSummary('InvoicePurchase', $in);
    }

    /** Totaux de factures émises sur N jours, et les cinq tiers les plus importants. */
    private function invoiceSummary(string $type, array $in): array
    {
        $days = max(1, min(90, (int) ($in['jours'] ?? 30)));
        $base = fn () => DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('d.deleted_at')->where('d.document_type', $type)->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('d.issued_at', '>=', now()->subDays($days)->toDateString());

        $tot = $base()->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->first();
        $top = $base()->join('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->groupBy('t.id', 't.tp_title')
            ->selectRaw('t.tp_title AS tiers, COUNT(*) AS factures, SUM(f.total_ttc) AS total_ttc')->orderByDesc('total_ttc')->limit(5)->get();

        return [
            'jours'       => $days,
            'factures'    => (int) $tot->n,
            'total_ttc'   => round((float) $tot->ttc, 2),
            'principaux'  => $top->map(fn ($r) => ['tiers' => $r->tiers, 'factures' => (int) $r->factures, 'total_ttc' => round((float) $r->total_ttc, 2)])->all(),
        ];
    }

    private function creances(): array
    {
        $base = fn () => DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoiceSale')->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('f.amount_due', '>', 0)->whereNotNull('d.due_at')->where('d.due_at', '<', now()->toDateString());

        $tot = $base()->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.amount_due), 0) AS due, MIN(d.due_at) AS oldest')->first();
        $rows = $base()->join('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->selectRaw('d.reference, t.tp_title AS client, d.due_at AS echeance, f.amount_due AS du')->orderByDesc('f.amount_due')->limit(10)->get();

        return [
            'factures_en_retard' => (int) $tot->n,
            'total_du'           => round((float) $tot->due, 2),
            'echeance_la_plus_ancienne' => $tot->oldest,
            'plus_importantes'   => $rows->map(fn ($r) => ['reference' => $r->reference, 'client' => $r->client, 'echeance' => (string) $r->echeance, 'du' => round((float) $r->du, 2)])->all(),
        ];
    }

    private function produitRecherche(array $in): array
    {
        $q = trim((string) ($in['recherche'] ?? ''));
        if (mb_strlen($q) < 2) {
            return ['erreur' => 'Recherche trop courte (2 caractères minimum).'];
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 60)) . '%';

        $rows = Product::query()->select(['id', 'p_title', 'p_sku', 'p_salePrice'])
            ->withSum('warehouseStocks as stock_qty', 'stockLevel')
            ->where(fn ($w) => $w->where('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->orderBy('p_title')->limit(10)->get();

        return ['resultats' => $rows->map(fn (Product $p) => [
            'titre' => $p->p_title, 'reference' => $p->p_sku, 'prix_vente' => round((float) $p->p_salePrice, 2), 'stock' => (float) ($p->stock_qty ?? 0),
        ])->all()];
    }

    private function qualiteFiches(): array
    {
        $r = app(CatalogAudit::class)->run();

        return ['fiches' => $r['total'], 'a_corriger' => count($r['flagged']), 'manques' => array_map('count', $r['issues'])];
    }

    /** @return array<string, array<string, mixed>> */
    private function allDefinitions(): array
    {
        $days = ['type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'description' => 'Nombre de jours à couvrir (30 par défaut).'];

        return [
            'stock_bas' => ['name' => 'stock_bas', 'description' => 'Produits dont le stock est bas ou en rupture (quantité inférieure ou égale au seuil), les plus bas d\'abord.',
                'input_schema' => ['type' => 'object', 'properties' => ['seuil' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'description' => 'Quantité maximale (5 par défaut).'], 'entrepot' => ['type' => 'string', 'description' => 'Nom exact d\'un entrepôt, sinon tous.']]]],
            'ventes_resume' => ['name' => 'ventes_resume', 'description' => 'Total des factures de vente émises sur une période et les cinq clients les plus importants.',
                'input_schema' => ['type' => 'object', 'properties' => ['jours' => $days]]],
            'creances' => ['name' => 'creances', 'description' => 'Factures de vente impayées dont l\'échéance est dépassée : total dû et les dix plus importantes.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()]],
            'achats_resume' => ['name' => 'achats_resume', 'description' => 'Total des factures d\'achat sur une période et les cinq fournisseurs les plus importants.',
                'input_schema' => ['type' => 'object', 'properties' => ['jours' => $days]]],
            'produit_recherche' => ['name' => 'produit_recherche', 'description' => 'Cherche des produits par titre ou référence (10 résultats au plus).',
                'input_schema' => ['type' => 'object', 'properties' => ['recherche' => ['type' => 'string']], 'required' => ['recherche']]],
            'qualite_fiches' => ['name' => 'qualite_fiches', 'description' => 'Nombre de fiches produits incomplètes, par type de manque.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()]],
        ];
    }
}
