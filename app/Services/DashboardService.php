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
use Illuminate\Support\Facades\DB;

class DashboardService
{
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

    // ── KPI cards with month-over-month trend ────────────────────────
    private function cards(Carbon $startMonth, Carbon $startPrev, Carbon $endPrev, Carbon $startToday): array
    {
        // Sales
        $caCurrent = $this->salesTotal($startMonth);
        $caPrev    = $this->salesTotal($startPrev, $endPrev);

        // Purchases
        $purchasesCurrent = $this->purchasesTotal($startMonth);
        $purchasesPrev    = $this->purchasesTotal($startPrev, $endPrev);

        // Payments received
        $paymentsCurrent = Payment::where('paid_at', '>=', $startMonth)
            ->whereHas('document', fn ($q) => $q->whereIn('document_type', ['InvoiceSale', 'TicketSale', 'DeliveryNote']))
            ->sum('amount');
        $paymentsPrev = Payment::whereBetween('paid_at', [$startPrev, $endPrev])
            ->whereHas('document', fn ($q) => $q->whereIn('document_type', ['InvoiceSale', 'TicketSale', 'DeliveryNote']))
            ->sum('amount');

        // Invoice count
        $invoicesCurrent = DocumentHeader::whereIn('document_type', ['InvoiceSale', 'TicketSale'])
            ->where('created_at', '>=', $startMonth)
            ->whereNotIn('status', ['cancelled'])
            ->count();
        $invoicesPrev = DocumentHeader::whereIn('document_type', ['InvoiceSale', 'TicketSale'])
            ->whereBetween('created_at', [$startPrev, $endPrev])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        // Outstanding
        $outstandingDue = DocumentFooter::whereHas('header', fn ($q) =>
            $q->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
              ->whereNotIn('status', ['paid', 'cancelled'])
        )->sum('amount_due');

        // Today's sales (all types)
        $todaySales = DocumentFooter::whereHas('header', fn ($q) =>
            $q->whereIn('document_type', ['InvoiceSale', 'TicketSale', 'DeliveryNote'])
              ->whereNotIn('status', ['cancelled'])
              ->where('created_at', '>=', $startToday)
        )->sum('total_ttc');

        // Margin estimate (sales - purchases this month)
        $marginCurrent = $caCurrent - $purchasesCurrent;

        // Counters
        $productCount   = Product::where('p_status', true)->count();
        $clientCount    = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])->where('tp_status', true)->count();
        $supplierCount  = ThirdPartner::whereIn('tp_Role', ['supplier', 'both'])->where('tp_status', true)->count();

        return [
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
                'key'      => 'margin_month',
                'label'    => 'Marge brute du mois',
                'value'    => round($marginCurrent, 2),
                'currency' => true,
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
    }

    // ── Revenue chart (last 12 months — sales) ──────────────────────
    private function revenueChart(): array
    {
        $rows = DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled'])
            ->where('document_headers.created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(document_headers.created_at, '%Y-%m') as month"),
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
                  ->whereBetween('created_at', [$from, $to])
            )->sum('total_ttc');

            $purchases = DocumentFooter::whereHas('header', fn ($q) =>
                $q->where('document_type', 'InvoicePurchase')
                  ->whereNotIn('status', ['cancelled'])
                  ->whereBetween('created_at', [$from, $to])
            )->sum('total_ttc');

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
            ->where('document_headers.created_at', '>=', $since)
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
            ->where('document_headers.created_at', '>=', $since)
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
            ->where('created_at', '>=', $startToday)
            ->whereNotIn('status', ['cancelled']);

        $ticketCount = (clone $tickets)->count();

        $totalTtc = DocumentFooter::whereHas('header', fn ($q) =>
            $q->where('document_type', 'TicketSale')
              ->whereNotIn('status', ['cancelled'])
              ->where('created_at', '>=', $startToday)
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
    private function salesTotal(Carbon $from, ?Carbon $to = null): float
    {
        $q = DocumentFooter::query()
            ->whereHas('header', function ($q) use ($from, $to) {
                $q->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
                  ->whereNotIn('status', ['cancelled']);
                if ($to) {
                    $q->whereBetween('created_at', [$from, $to]);
                } else {
                    $q->where('created_at', '>=', $from);
                }
            });

        return (float) $q->sum('total_ttc');
    }

    private function purchasesTotal(Carbon $from, ?Carbon $to = null): float
    {
        $q = DocumentFooter::query()
            ->whereHas('header', function ($q) use ($from, $to) {
                $q->where('document_type', 'InvoicePurchase')
                  ->whereNotIn('status', ['cancelled']);
                if ($to) {
                    $q->whereBetween('created_at', [$from, $to]);
                } else {
                    $q->where('created_at', '>=', $from);
                }
            });

        return (float) $q->sum('total_ttc');
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
