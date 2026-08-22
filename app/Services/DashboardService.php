<?php

namespace App\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\WarehouseHasStock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** Documents that represent a real sale to a customer. */
    private const SALE_TYPES = ['TicketSale', 'InvoiceSale', 'DeliveryNote'];

    /**
     * Statuses that must never land in a total: not yet committed (draft),
     * void (cancelled), or already superseded by the document it became
     * (converted — the successor carries the amount).
     */
    private const NON_COUNTING_STATUSES = ['cancelled', 'draft', 'converted'];

    public function getKpis(): array
    {
        return CacheService::remember(
            CacheService::dashboardKey(),
            CacheService::TTL_SHORT,
            fn () => $this->buildKpis()
        );
    }

    private function buildKpis(): array
    {
        $now        = Carbon::now();
        $startMonth = $now->copy()->startOfMonth();
        $startPrev  = $now->copy()->subMonth()->startOfMonth();
        $endPrev    = $now->copy()->subMonth()->endOfMonth();
        $startToday = $now->copy()->startOfDay();

        return [
            'cards'              => $this->cards($startMonth, $startPrev, $endPrev, $startToday),
            'revenue_chart'      => $this->revenueChart(),
            'sales_purchases_chart' => $this->salesPurchasesChart(),
            'payment_methods'    => $this->paymentMethodsBreakdown($startMonth),
            'top_products'       => $this->topProducts($startMonth),
            'low_stock'          => $this->lowStock(),
            'recent_documents'   => $this->recentDocuments(),
            'pending_orders'     => $this->pendingOrders(),
            'top_clients'        => $this->topClients($startMonth),
            'credit_clients'     => $this->creditClients(),
            'bl_to_invoice'      => $this->blToInvoice(),
            'pos_today'          => $this->posToday($startToday),
            'overdue_invoices'   => $this->overdueInvoices(),
        ];
    }

    /**
     * How each card is rendered. Sent explicitly rather than inferred from the
     * card's position, so hiding one from the widget manager cannot push the
     * next card into a row that renders it differently.
     */
    private const CARD_GROUPS = [
        'ca_month'        => 'main',
        'purchases_month' => 'main',
        'payments_month'  => 'main',
        'outstanding'     => 'main',
        'today_sales'     => 'secondary',
        'margin_today'    => 'secondary',
        'margin_month'    => 'secondary',
        'invoices_month'  => 'secondary',
        'products'        => 'pill',
        'clients'         => 'pill',
        'suppliers'       => 'pill',
    ];

    // ── KPI cards with month-over-month trend ────────────────────────
    private function cards(Carbon $startMonth, Carbon $startPrev, Carbon $endPrev, Carbon $startToday): array
    {
        // Sales
        $caCurrent = $this->salesTotal($startMonth);
        $caPrev    = $this->salesTotal($startPrev, $endPrev);

        // Purchases
        $purchasesCurrent = $this->purchasesTotal($startMonth);
        $purchasesPrev    = $this->purchasesTotal($startPrev, $endPrev);

        // Payments received.
        //
        // `credit` is excluded: a POS "en compte" line is written as a Payment
        // row so the ticket balances, but no money changes hands — it is a
        // receivable. Counting it here inflated the takings by the exact
        // amount that also sits in amount_due, so the same dirham was reported
        // as both collected and owed. It is surfaced separately in `meta`.
        $paymentsCurrent = $this->collected($startMonth);
        $paymentsPrev    = $this->collected($startPrev, $endPrev);
        $creditCurrent   = $this->creditGranted($startMonth);

        // Invoice count
        $invoicesCurrent = DocumentHeader::whereIn('document_type', ['InvoiceSale', 'TicketSale'])
            ->where('issued_at', '>=', $startMonth)
            ->whereNotIn('status', ['cancelled'])
            ->count();
        $invoicesPrev = DocumentHeader::whereIn('document_type', ['InvoiceSale', 'TicketSale'])
            ->whereBetween('issued_at', [$startPrev, $endPrev])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        // Outstanding.
        //
        // DeliveryNote must be in the list: a POS credit sale is stored as one
        // (PosService::createTicket), so the amount_due of every "en compte"
        // sale used to sit on a type this card excluded — the figure read 0
        // while real receivables were outstanding.
        //
        // A delivery note already turned into an invoice is skipped: the
        // invoice carries the same amount_due, and counting both would double
        // it. Same guard as marginBreakdown() and DocumentHeader::isBilled().
        $outstandingDue = DocumentFooter::whereHas('header', fn ($q) =>
            $q->whereIn('document_type', self::SALE_TYPES)
              ->whereNotIn('status', ['paid', 'cancelled'])
              ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))
                  ->from('document_headers as inv')
                  ->whereColumn('inv.parent_id', 'document_headers.id')
                  ->where('inv.document_type', 'InvoiceSale'))
        )->sum('amount_due');

        // Today's sales (all types)
        $todaySales = DocumentFooter::whereHas('header', fn ($q) =>
            $q->whereIn('document_type', ['InvoiceSale', 'TicketSale', 'DeliveryNote'])
              ->whereNotIn('status', ['cancelled'])
              ->where('issued_at', '>=', $startToday)
        )->sum('total_ttc');

        // Gross margin on the goods actually sold this month.
        $marginCurrent = $this->marginBreakdown($startMonth);
        $marginPrev    = $this->marginBreakdown($startPrev, $endPrev);

        // Same figure for today, compared against yesterday.
        $marginToday     = $this->marginBreakdown($startToday);
        $marginYesterday = $this->marginBreakdown(
            $startToday->copy()->subDay(),
            $startToday->copy()->subSecond(),
        );

        // Counters
        $productCount   = Product::where('p_status', true)->count();
        $clientCount    = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->where('tp_status', true)->count();
        $supplierCount  = ThirdPartner::whereIn('tp_Role', ['supplier', 'both'])->where('tp_status', true)->count();

        $cards = [
            // Main 4 cards (row 1)
            [
                'key'      => 'ca_month',
                'label'    => 'CA Ventes du mois',
                'value'    => round($caCurrent, 2),
                'prev'     => round($caPrev, 2),
                'trend'    => $this->trend($caCurrent, $caPrev),
                'currency' => true,
            ],
            [
                'key'      => 'purchases_month',
                'label'    => 'Achats du mois',
                'value'    => round($purchasesCurrent, 2),
                'prev'     => round($purchasesPrev, 2),
                'trend'    => $this->trend($purchasesCurrent, $purchasesPrev),
                'currency' => true,
            ],
            [
                'key'      => 'payments_month',
                'label'    => 'Encaissements du mois',
                'value'    => round($paymentsCurrent, 2),
                'prev'     => round($paymentsPrev, 2),
                'trend'    => $this->trend($paymentsCurrent, $paymentsPrev),
                'currency' => true,
                'meta'     => [
                    // Ventes en compte du mois : accordées, pas encaissées.
                    'credit_granted' => round($creditCurrent, 2),
                ],
            ],
            [
                'key'      => 'outstanding',
                'label'    => 'Créances en cours',
                'value'    => round($outstandingDue, 2),
                'prev'     => null,
                'trend'    => null,
                'currency' => true,
            ],
            // Row 2 (4 more cards)
            [
                'key'      => 'today_sales',
                'label'    => "Ventes aujourd'hui",
                'value'    => round($todaySales, 2),
                'currency' => true,
            ],
            [
                'key'      => 'margin_today',
                'label'    => "Bénéfice aujourd'hui",
                'value'    => round($marginToday['margin'], 2),
                'prev'     => round($marginYesterday['margin'], 2),
                'trend'    => $this->trend($marginToday['margin'], $marginYesterday['margin']),
                'currency' => true,
                'meta'     => [
                    'revenue_ht'     => round($marginToday['revenue'], 2),
                    'cogs'           => round($marginToday['cogs'], 2),
                    'rate'           => $marginToday['revenue'] > 0
                        ? round($marginToday['margin'] / $marginToday['revenue'] * 100, 1)
                        : null,
                    'uncosted_lines' => $marginToday['uncosted_lines'],
                ],
            ],
            [
                'key'      => 'margin_month',
                'label'    => 'Marge brute du mois',
                'value'    => round($marginCurrent['margin'], 2),
                'prev'     => round($marginPrev['margin'], 2),
                'trend'    => $this->trend($marginCurrent['margin'], $marginPrev['margin']),
                'currency' => true,
                'meta'     => [
                    'revenue_ht'     => round($marginCurrent['revenue'], 2),
                    'cogs'           => round($marginCurrent['cogs'], 2),
                    'rate'           => $marginCurrent['revenue'] > 0
                        ? round($marginCurrent['margin'] / $marginCurrent['revenue'] * 100, 1)
                        : null,
                    // Lines whose product carries neither cost nor purchase
                    // price: they inflate the margin, so the UI can warn.
                    'uncosted_lines' => $marginCurrent['uncosted_lines'],
                ],
            ],
            [
                'key'      => 'invoices_month',
                'label'    => 'Documents du mois',
                'value'    => $invoicesCurrent,
                'prev'     => $invoicesPrev,
                'trend'    => $this->trend($invoicesCurrent, $invoicesPrev),
                'currency' => false,
            ],
            [
                'key'   => 'products',
                'label' => 'Produits actifs',
                'value' => $productCount,
                'currency' => false,
            ],
            // Secondary pills
            [
                'key'   => 'clients',
                'label' => 'Clients',
                'value' => $clientCount,
            ],
            [
                'key'   => 'suppliers',
                'label' => 'Fournisseurs',
                'value' => $supplierCount,
            ],
        ];

        return array_map(
            fn (array $card) => $card + ['group' => self::CARD_GROUPS[$card['key']] ?? 'pill'],
            $cards,
        );
    }

    // ── Revenue chart (last 12 months — sales) ──────────────────────
    private function revenueChart(): array
    {
        $rows = DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled'])
            ->where('document_headers.issued_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(document_headers.issued_at, '%Y-%m') as month"),
                DB::raw('SUM(document_footers.total_ttc) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i)->format('Y-m');
            $months->put($m, 0);
        }

        foreach ($rows as $row) {
            $months->put($row->month, round($row->total, 2));
        }

        return $months->map(fn ($total, $month) => [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M Y'),
            'total' => $total,
        ])->values()->all();
    }

    // ── Sales vs Purchases chart (last 6 months) ────────────────────
    private function salesPurchasesChart(): array
    {
        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $from  = Carbon::now()->subMonths($i)->startOfMonth();
            $to    = Carbon::now()->subMonths($i)->endOfMonth();
            $label = $from->translatedFormat('M Y');

            $sales = DocumentFooter::whereHas('header', fn ($q) =>
                $q->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
                  ->whereNotIn('status', ['cancelled'])
                  ->whereBetween('issued_at', [$from, $to])
            )->sum('total_ttc');

            $purchases = DocumentFooter::whereHas('header', function ($q) use ($from, $to) {
                $this->scopePurchases($q);
                $q->whereBetween('issued_at', [$from, $to]);
            })->sum('total_ttc');

            $result[] = [
                'label'     => $label,
                'sales'     => round($sales, 2),
                'purchases' => round($purchases, 2),
            ];
        }
        return $result;
    }

    // ── Payment methods breakdown this month ─────────────────────────
    private function paymentMethodsBreakdown(Carbon $since): array
    {
        return Payment::where('paid_at', '>=', $since)
            ->whereHas('document', fn ($q) => $q->whereNotIn('status', ['cancelled']))
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($p) => [
                'method' => $p->method,
                'label'  => $this->paymentMethodLabel($p->method),
                'total'  => round($p->total, 2),
                'count'  => $p->count,
            ])
            ->toArray();
    }

    // ── Top 10 products by revenue this month ──────────────────────
    private function topProducts(Carbon $since): array
    {
        return DocumentLigne::query()
            ->join('document_headers', 'document_lignes.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled'])
            ->where('document_headers.issued_at', '>=', $since)
            ->select(
                'document_lignes.product_id',
                'document_lignes.designation',
                DB::raw('SUM(document_lignes.quantity) as total_qty'),
                DB::raw('SUM(document_lignes.quantity * document_lignes.unit_price) as total_revenue')
            )
            ->groupBy('document_lignes.product_id', 'document_lignes.designation')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->toArray();
    }

    // ── Low stock products ──────────────────────────────────────────
    private function lowStock(): array
    {
        return WarehouseHasStock::with(['product:id,p_title,p_sku', 'warehouse:id,wh_title'])
            ->where('stockLevel', '<=', 5)
            ->orderBy('stockLevel')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'product'    => $s->product?->p_title,
                'sku'        => $s->product?->p_sku,
                'warehouse'  => $s->warehouse?->wh_title,
                'stockLevel' => round($s->stockLevel, 2),
            ])
            ->toArray();
    }

    // ── Last 10 documents created ──────────────────────────────────
    private function recentDocuments(): array
    {
        return DocumentHeader::with(['thirdPartner:id,tp_title', 'footer:id,document_header_id,total_ttc'])
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'document_type', 'status', 'thirdPartner_id', 'created_at'])
            ->toArray();
    }

    // ── Pending / unpaid invoices ──────────────────────────────────
    private function pendingOrders(): array
    {
        return DocumentHeader::with(['thirdPartner:id,tp_title', 'footer:id,document_header_id,total_ttc,amount_due'])
            ->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
            ->whereIn('status', ['confirmed', 'partial', 'pending'])
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'document_type', 'status', 'thirdPartner_id', 'created_at'])
            ->toArray();
    }

    // ── Top 5 clients by revenue this month ────────────────────────
    private function topClients(Carbon $since): array
    {
        return DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->join('third_partners', 'document_headers.thirdPartner_id', '=', 'third_partners.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled'])
            ->where('document_headers.issued_at', '>=', $since)
            ->where('third_partners.tp_code', '!=', 'CLIENT-COMPTOIR')
            ->select(
                'third_partners.id',
                'third_partners.tp_title',
                DB::raw('SUM(document_footers.total_ttc) as total_revenue'),
                DB::raw('COUNT(document_headers.id) as invoice_count')
            )
            ->groupBy('third_partners.id', 'third_partners.tp_title')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->toArray();
    }

    // ── Clients "en compte" with outstanding credit ─────────────────
    private function creditClients(): array
    {
        return ThirdPartner::where('type_compte', 'en_compte')
            ->where('encours_actuel', '>', 0)
            ->select('id', 'tp_title', 'encours_actuel', 'seuil_credit')
            ->orderByDesc('encours_actuel')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'tp_title'       => $c->tp_title,
                'encours_actuel' => round(floatval($c->encours_actuel), 2),
                'seuil_credit'   => round(floatval($c->seuil_credit), 2),
                'usage_pct'      => $c->seuil_credit > 0
                    ? round(($c->encours_actuel / $c->seuil_credit) * 100, 1)
                    : null,
            ])
            ->toArray();
    }

    // ── BL (DeliveryNotes) pending invoice conversion ───────────────
    private function blToInvoice(): array
    {
        return DocumentHeader::with(['thirdPartner:id,tp_title', 'footer:id,document_header_id,total_ttc'])
            ->where('document_type', 'DeliveryNote')
            ->where('status', 'confirmed')
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'status', 'thirdPartner_id', 'created_at'])
            ->toArray();
    }

    // ── POS today summary ───────────────────────────────────────────
    private function posToday(Carbon $startToday): array
    {
        $tickets = DocumentHeader::where('document_type', 'TicketSale')
            ->where('issued_at', '>=', $startToday)
            ->whereNotIn('status', ['cancelled']);

        $ticketCount = (clone $tickets)->count();

        $totalTtc = DocumentFooter::whereHas('header', fn ($q) =>
            $q->where('document_type', 'TicketSale')
              ->whereNotIn('status', ['cancelled'])
              ->where('issued_at', '>=', $startToday)
        )->sum('total_ttc');

        // Active sessions
        $activeSessions = PosSession::whereNull('closed_at')
            ->with('terminal:id,name', 'user:id,name')
            ->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'terminal'  => $s->terminal?->name ?? '—',
                'user'      => $s->user?->name ?? '—',
                'opened_at' => $s->opened_at,
            ])
            ->toArray();

        return [
            'ticket_count'    => $ticketCount,
            'total_ttc'       => round($totalTtc, 2),
            'active_sessions' => $activeSessions,
        ];
    }

    // ── Overdue invoices (past due_at) ──────────────────────────────
    private function overdueInvoices(): array
    {
        return DocumentHeader::with(['thirdPartner:id,tp_title', 'footer:id,document_header_id,total_ttc,amount_due'])
            ->where('document_type', 'InvoiceSale')
            ->whereIn('status', ['confirmed', 'partial', 'pending'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->orderBy('due_at')
            ->limit(10)
            ->get(['id', 'reference', 'status', 'thirdPartner_id', 'due_at', 'created_at'])
            ->toArray();
    }

    // ── Helpers ─────────────────────────────────────────────────────
    /**
     * Money actually received over a window: every payment method except
     * `credit`, which records a sale on account, not a collection.
     */
    private function collected(Carbon $from, ?Carbon $to = null): float
    {
        return (float) $this->paymentsOnSales($from, $to)
            ->where('method', '!=', 'credit')
            ->sum('amount');
    }

    /**
     * The counterpart of collected(): credit granted over the same window.
     */
    private function creditGranted(Carbon $from, ?Carbon $to = null): float
    {
        return (float) $this->paymentsOnSales($from, $to)
            ->where('method', 'credit')
            ->sum('amount');
    }

    private function paymentsOnSales(Carbon $from, ?Carbon $to = null): Builder
    {
        return Payment::query()
            ->when(
                $to,
                fn ($q) => $q->whereBetween('paid_at', [$from, $to]),
                fn ($q) => $q->where('paid_at', '>=', $from),
            )
            ->whereHas('document', fn ($q) => $q->whereIn('document_type', self::SALE_TYPES));
    }

    private function salesTotal(Carbon $from, ?Carbon $to = null): float
    {
        $q = DocumentFooter::query()
            ->whereHas('header', function ($q) use ($from, $to) {
                $q->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
                  ->whereNotIn('status', ['cancelled']);
                if ($to) {
                    $q->whereBetween('issued_at', [$from, $to]);
                } else {
                    $q->where('issued_at', '>=', $from);
                }
            });

        return (float) $q->sum('total_ttc');
    }

    private function purchasesTotal(Carbon $from, ?Carbon $to = null): float
    {
        return (float) DocumentFooter::query()
            ->whereHas('header', function ($q) use ($from, $to) {
                $this->scopePurchases($q);
                if ($to) {
                    $q->whereBetween('issued_at', [$from, $to]);
                } else {
                    $q->where('issued_at', '>=', $from);
                }
            })
            ->sum('total_ttc');
    }

    /**
     * What counts as buying this month: purchase invoices, plus receipt notes
     * that are confirmed but not yet invoiced. A purchase *order* is only an
     * intent to buy, so it no longer counts — it used to, which inflated the
     * figure with goods that may never have arrived.
     *
     * A receipt note already turned into an invoice is skipped: its amount
     * comes back through that invoice (same guard as the sales side).
     */
    private function scopePurchases($q): void
    {
        $q->whereNotIn('status', self::NON_COUNTING_STATUSES)
          ->where(function ($q2) {
              $q2->where('document_type', 'InvoicePurchase')
                 ->orWhere(fn ($q3) => $q3
                     ->where('document_type', 'ReceiptNotePurchase')
                     ->whereDoesntHave('children', fn ($c) => $c->where('document_type', 'InvoicePurchase')));
          });
    }

    /**
     * Gross margin on what was actually sold: the HT total of every sale line
     * (discounts applied, VAT excluded) minus the cost of those very goods.
     *
     * This used to be `sales TTC - purchases TTC`, which measured something
     * else entirely — restocking done in a month has no relation to what that
     * month sold, and VAT belongs to neither side of a margin.
     *
     * Cost basis is p_cost (coût de revient), falling back to p_purchasePrice
     * when it is not filled in; lines with neither are counted separately so
     * the figure can be qualified rather than silently overstated.
     *
     * @return array{revenue: float, cogs: float, margin: float, uncosted_lines: int}
     */
    private function marginBreakdown(Carbon $from, ?Carbon $to = null): array
    {
        $cost = 'COALESCE(NULLIF(p.p_cost, 0), NULLIF(p.p_purchasePrice, 0), 0)';

        $row = DocumentLigne::query()
            ->join('document_headers as h', 'h.id', '=', 'document_lignes.document_header_id')
            ->leftJoin('products as p', 'p.id', '=', 'document_lignes.product_id')
            ->where('document_lignes.line_type', 'product')
            ->where('document_lignes.status', 'active')
            ->whereIn('h.document_type', self::SALE_TYPES)
            ->whereNotIn('h.status', self::NON_COUNTING_STATUSES)
            // A delivery note already turned into an invoice is counted through
            // that invoice — same rule as DocumentHeader::isBilled().
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('document_headers as inv')
                ->whereColumn('inv.parent_id', 'h.id')
                ->where('inv.document_type', 'InvoiceSale'))
            ->when(
                $to,
                fn ($q) => $q->whereBetween('h.issued_at', [$from, $to]),
                fn ($q) => $q->where('h.issued_at', '>=', $from),
            )
            ->selectRaw("
                COALESCE(SUM(document_lignes.total_ligne_ht), 0)        AS revenue,
                COALESCE(SUM(document_lignes.quantity * {$cost}), 0)    AS cogs,
                COALESCE(SUM(CASE WHEN {$cost} = 0 THEN 1 ELSE 0 END), 0) AS uncosted
            ")
            ->first();

        $revenue = (float) ($row->revenue ?? 0);
        $cogs    = (float) ($row->cogs ?? 0);

        return [
            'revenue'        => $revenue,
            'cogs'           => $cogs,
            'margin'         => $revenue - $cogs,
            'uncosted_lines' => (int) ($row->uncosted ?? 0),
        ];
    }

    private function trend(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round(($current - $previous) / $previous * 100, 1);
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash'          => 'Espèces',
            'card'          => 'Carte bancaire',
            'credit'        => 'En Compte',
            'cheque'        => 'Chèque',
            'bank_transfer' => 'Virement',
            'effet'         => 'Effet',
            default         => ucfirst($method),
        };
    }
}
