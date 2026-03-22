<?php

namespace App\Services;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\StockMouvement;
use App\Models\WarehouseHasStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // ── Document type groups ──────────────────────────────────────────
    private const SALE_TYPES = [
        'QuoteSale', 'CustomerOrder', 'DeliveryNote',
        'InvoiceSale', 'CreditNoteSale', 'ReturnSale', 'TicketSale',
    ];

    private const PURCHASE_TYPES = [
        'PurchaseOrder', 'ReceiptNotePurchase',
        'InvoicePurchase', 'CreditNotePurchase', 'ReturnPurchase',
    ];

    private const TYPE_LABELS = [
        'QuoteSale'            => 'Devis',
        'CustomerOrder'        => 'Bon de Commande',
        'DeliveryNote'         => 'Bon de Livraison',
        'InvoiceSale'          => 'Facture Vente',
        'CreditNoteSale'       => 'Avoir Client',
        'ReturnSale'           => 'Retour Client',
        'TicketSale'           => 'Ticket POS',
        'PurchaseOrder'        => 'Bon de Commande Fournisseur',
        'ReceiptNotePurchase'  => 'Bon de Réception',
        'InvoicePurchase'      => 'Facture Achat',
        'CreditNotePurchase'   => 'Avoir Fournisseur',
        'ReturnPurchase'       => 'Retour Fournisseur',
    ];

    // ═══════════════════════════════════════════════════════════════════
    //  SALES REPORT
    // ═══════════════════════════════════════════════════════════════════

    public function salesReport(Carbon $from, Carbon $to): array
    {
        return [
            'period'             => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals'             => $this->salesTotals($from, $to),
            'by_type'            => $this->countByType(self::SALE_TYPES, $from, $to),
            'by_status'          => $this->countByStatus(self::SALE_TYPES, $from, $to),
            'top_products'       => $this->topProductsByRevenue($from, $to),
            'top_clients'        => $this->topClientsByRevenue($from, $to),
            'payments_by_method' => $this->paymentsByMethod(self::SALE_TYPES, $from, $to),
            'daily_revenue'      => $this->dailyRevenue($from, $to),
        ];
    }

    private function salesTotals(Carbon $from, Carbon $to): array
    {
        $invoiceTypes = ['InvoiceSale', 'TicketSale'];

        $row = DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', $invoiceTypes)
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                DB::raw('COALESCE(SUM(document_footers.total_ttc), 0) as revenue_ttc'),
                DB::raw('COALESCE(SUM(document_footers.total_ht), 0)  as revenue_ht'),
                DB::raw('COALESCE(SUM(document_footers.total_tax), 0) as total_tax'),
                DB::raw('COALESCE(SUM(document_footers.total_discount), 0) as total_discount'),
                DB::raw('COUNT(document_headers.id) as invoice_count')
            )
            ->first();

        return [
            'revenue_ttc'    => round((float) $row->revenue_ttc, 2),
            'revenue_ht'     => round((float) $row->revenue_ht, 2),
            'total_tax'      => round((float) $row->total_tax, 2),
            'total_discount' => round((float) $row->total_discount, 2),
            'invoice_count'  => (int) $row->invoice_count,
        ];
    }

    private function topProductsByRevenue(Carbon $from, Carbon $to): array
    {
        return DocumentLigne::query()
            ->join('document_headers', 'document_lignes.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                'document_lignes.product_id',
                'document_lignes.designation',
                DB::raw('SUM(document_lignes.quantity)  as total_qty'),
                DB::raw('SUM(document_lignes.total_ttc) as total_revenue')
            )
            ->groupBy('document_lignes.product_id', 'document_lignes.designation')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function topClientsByRevenue(Carbon $from, Carbon $to): array
    {
        return DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->join('third_partners', 'document_headers.thirdPartner_id', '=', 'third_partners.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                'third_partners.id',
                'third_partners.tp_title',
                DB::raw('SUM(document_footers.total_ttc) as total_revenue'),
                DB::raw('COUNT(document_headers.id) as invoice_count')
            )
            ->groupBy('third_partners.id', 'third_partners.tp_title')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function dailyRevenue(Carbon $from, Carbon $to): array
    {
        return DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoiceSale', 'TicketSale'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                DB::raw("DATE(document_headers.created_at) as day"),
                DB::raw('SUM(document_footers.total_ttc) as total')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'   => $r->day,
                'total' => round((float) $r->total, 2),
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PURCHASES REPORT
    // ═══════════════════════════════════════════════════════════════════

    public function purchasesReport(Carbon $from, Carbon $to): array
    {
        return [
            'period'             => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals'             => $this->purchasesTotals($from, $to),
            'by_type'            => $this->countByType(self::PURCHASE_TYPES, $from, $to),
            'by_status'          => $this->countByStatus(self::PURCHASE_TYPES, $from, $to),
            'top_products'       => $this->topPurchasedProducts($from, $to),
            'top_suppliers'      => $this->topSuppliersByAmount($from, $to),
            'payments_by_method' => $this->paymentsByMethod(self::PURCHASE_TYPES, $from, $to),
            'daily_spending'     => $this->dailySpending($from, $to),
        ];
    }

    private function purchasesTotals(Carbon $from, Carbon $to): array
    {
        $row = DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoicePurchase'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                DB::raw('COALESCE(SUM(document_footers.total_ttc), 0) as spending_ttc'),
                DB::raw('COALESCE(SUM(document_footers.total_ht), 0)  as spending_ht'),
                DB::raw('COALESCE(SUM(document_footers.total_tax), 0) as total_tax'),
                DB::raw('COALESCE(SUM(document_footers.total_discount), 0) as total_discount'),
                DB::raw('COUNT(document_headers.id) as invoice_count')
            )
            ->first();

        return [
            'spending_ttc'   => round((float) $row->spending_ttc, 2),
            'spending_ht'    => round((float) $row->spending_ht, 2),
            'total_tax'      => round((float) $row->total_tax, 2),
            'total_discount' => round((float) $row->total_discount, 2),
            'invoice_count'  => (int) $row->invoice_count,
        ];
    }

    private function topPurchasedProducts(Carbon $from, Carbon $to): array
    {
        return DocumentLigne::query()
            ->join('document_headers', 'document_lignes.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoicePurchase', 'ReceiptNotePurchase'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                'document_lignes.product_id',
                'document_lignes.designation',
                DB::raw('SUM(document_lignes.quantity)  as total_qty'),
                DB::raw('SUM(document_lignes.total_ttc) as total_cost')
            )
            ->groupBy('document_lignes.product_id', 'document_lignes.designation')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function topSuppliersByAmount(Carbon $from, Carbon $to): array
    {
        return DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->join('third_partners', 'document_headers.thirdPartner_id', '=', 'third_partners.id')
            ->whereIn('document_headers.document_type', ['InvoicePurchase'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                'third_partners.id',
                'third_partners.tp_title',
                DB::raw('SUM(document_footers.total_ttc) as total_amount'),
                DB::raw('COUNT(document_headers.id) as invoice_count')
            )
            ->groupBy('third_partners.id', 'third_partners.tp_title')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function dailySpending(Carbon $from, Carbon $to): array
    {
        return DocumentFooter::query()
            ->join('document_headers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', ['InvoicePurchase'])
            ->whereNotIn('document_headers.status', ['cancelled', 'draft'])
            ->whereBetween('document_headers.created_at', [$from, $to])
            ->select(
                DB::raw("DATE(document_headers.created_at) as day"),
                DB::raw('SUM(document_footers.total_ttc) as total')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'   => $r->day,
                'total' => round((float) $r->total, 2),
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STOCK REPORT
    // ═══════════════════════════════════════════════════════════════════

    public function stockReport(?int $warehouseId = null): array
    {
        return [
            'current_stock'      => $this->currentStock($warehouseId),
            'total_value'        => $this->stockValue($warehouseId),
            'low_stock'          => $this->lowStockItems($warehouseId),
            'out_of_stock'       => $this->outOfStockItems($warehouseId),
            'movements_summary'  => $this->movementsSummary($warehouseId),
        ];
    }

    private function currentStock(?int $warehouseId): array
    {
        $query = WarehouseHasStock::with(['product:id,p_title,p_sku,p_cost', 'warehouse:id,wh_title'])
            ->where('stockLevel', '>', 0)
            ->orderBy('stockLevel', 'desc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(fn ($s) => [
                'product'    => $s->product?->p_title,
                'sku'        => $s->product?->p_sku,
                'warehouse'  => $s->warehouse?->wh_title,
                'stockLevel' => round((float) $s->stockLevel, 2),
                'cost_price' => round((float) ($s->product?->p_cost ?? 0), 2),
                'value'      => round((float) $s->stockLevel * ($s->product?->p_cost ?? 0), 2),
            ])
            ->toArray();
    }

    private function stockValue(?int $warehouseId): array
    {
        $query = WarehouseHasStock::query()
            ->join('products', 'warehouse_has_stock.product_id', '=', 'products.id')
            ->where('warehouse_has_stock.stockLevel', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_has_stock.warehouse_id', $warehouseId);
        }

        $row = $query->select(
            DB::raw('SUM(warehouse_has_stock.stockLevel) as total_qty'),
            DB::raw('SUM(warehouse_has_stock.stockLevel * products.p_cost) as total_value'),
            DB::raw('COUNT(DISTINCT warehouse_has_stock.product_id) as product_count')
        )->first();

        return [
            'total_qty'     => round((float) ($row->total_qty ?? 0), 2),
            'total_value'   => round((float) ($row->total_value ?? 0), 2),
            'product_count' => (int) ($row->product_count ?? 0),
        ];
    }

    private function lowStockItems(?int $warehouseId): array
    {
        $query = WarehouseHasStock::with(['product:id,p_title,p_sku', 'warehouse:id,wh_title'])
            ->where('stockLevel', '>', 0)
            ->where('stockLevel', '<=', 5)
            ->orderBy('stockLevel');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(fn ($s) => [
                'product'    => $s->product?->p_title,
                'sku'        => $s->product?->p_sku,
                'warehouse'  => $s->warehouse?->wh_title,
                'stockLevel' => round((float) $s->stockLevel, 2),
            ])
            ->toArray();
    }

    private function outOfStockItems(?int $warehouseId): array
    {
        $query = WarehouseHasStock::with(['product:id,p_title,p_sku', 'warehouse:id,wh_title'])
            ->where('stockLevel', '<=', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(fn ($s) => [
                'product'    => $s->product?->p_title,
                'sku'        => $s->product?->p_sku,
                'warehouse'  => $s->warehouse?->wh_title,
                'stockLevel' => round((float) $s->stockLevel, 2),
            ])
            ->toArray();
    }

    private function movementsSummary(?int $warehouseId): array
    {
        $query = StockMouvement::query();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query
            ->select(
                'direction',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_qty')
            )
            ->groupBy('direction')
            ->get()
            ->map(fn ($r) => [
                'direction' => $r->direction,
                'count'     => (int) $r->count,
                'total_qty' => round((float) $r->total_qty, 2),
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SHARED HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function countByType(array $types, Carbon $from, Carbon $to): array
    {
        return DocumentHeader::query()
            ->whereIn('document_type', $types)
            ->whereBetween('created_at', [$from, $to])
            ->select('document_type', DB::raw('COUNT(*) as count'))
            ->groupBy('document_type')
            ->get()
            ->map(fn ($r) => [
                'type'  => $r->document_type,
                'label' => self::TYPE_LABELS[$r->document_type] ?? $r->document_type,
                'count' => (int) $r->count,
            ])
            ->toArray();
    }

    private function countByStatus(array $types, Carbon $from, Carbon $to): array
    {
        return DocumentHeader::query()
            ->whereIn('document_type', $types)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'count'  => (int) $r->count,
            ])
            ->toArray();
    }

    private function paymentsByMethod(array $types, Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->join('document_headers', 'payments.document_header_id', '=', 'document_headers.id')
            ->whereIn('document_headers.document_type', $types)
            ->whereBetween('payments.paid_at', [$from, $to])
            ->select(
                'payments.method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('payments.method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count'  => (int) $r->count,
                'total'  => round((float) $r->total, 2),
            ])
            ->toArray();
    }
}
