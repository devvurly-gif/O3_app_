<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    // ── JSON endpoints ────────────────────────────────────────────────

    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDates($request);
        return response()->json($this->reportService->salesReport($from, $to));
    }

    public function purchases(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseDates($request);
        return response()->json($this->reportService->purchasesReport($from, $to));
    }

    public function stock(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        return response()->json($this->reportService->stockReport($warehouseId));
    }

    // ── PDF endpoints ─────────────────────────────────────────────────

    public function salesPdf(Request $request): Response
    {
        [$from, $to] = $this->parseDates($request);
        $data = $this->reportService->salesReport($from, $to);

        $pdf = Pdf::loadView('pdf.report-sales', [
            'data'    => $data,
            'company' => $this->getCompanyInfo(),
            'from'    => $from,
            'to'      => $to,
        ])->setPaper('a4', 'portrait');

        $filename = "Rapport_Ventes_{$from->format('Ymd')}_{$to->format('Ymd')}.pdf";
        return $pdf->download($filename);
    }

    public function purchasesPdf(Request $request): Response
    {
        [$from, $to] = $this->parseDates($request);
        $data = $this->reportService->purchasesReport($from, $to);

        $pdf = Pdf::loadView('pdf.report-purchases', [
            'data'    => $data,
            'company' => $this->getCompanyInfo(),
            'from'    => $from,
            'to'      => $to,
        ])->setPaper('a4', 'portrait');

        $filename = "Rapport_Achats_{$from->format('Ymd')}_{$to->format('Ymd')}.pdf";
        return $pdf->download($filename);
    }

    public function stockPdf(Request $request): Response
    {
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $data = $this->reportService->stockReport($warehouseId);

        $pdf = Pdf::loadView('pdf.report-stock', [
            'data'      => $data,
            'company'   => $this->getCompanyInfo(),
            'warehouse' => $warehouseId ? "Entrepôt #$warehouseId" : 'Tous les entrepôts',
        ])->setPaper('a4', 'portrait');

        $filename = "Rapport_Stock_" . now()->format('Ymd') . ".pdf";
        return $pdf->download($filename);
    }

    // ── Credit / Receivables ─────────────────────────────────────────

    public function creditClients(Request $request): JsonResponse
    {
        $clients = \App\Models\ThirdPartner::where('type_compte', 'en_compte')
            ->where('encours_actuel', '>', 0)
            ->orderByDesc('encours_actuel')
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'name'            => $c->tp_title,
                'code'            => $c->tp_code,
                'phone'           => $c->tp_phone,
                'email'           => $c->tp_email,
                'encours'         => (float) $c->encours_actuel,
                'seuil'           => (float) $c->seuil_credit,
                'usage_pct'       => $c->seuil_credit > 0
                    ? round(($c->encours_actuel / $c->seuil_credit) * 100, 1)
                    : 100,
                'status'          => $c->seuil_credit > 0 && $c->encours_actuel >= $c->seuil_credit
                    ? 'depasse' : ($c->seuil_credit > 0 && $c->encours_actuel >= $c->seuil_credit * 0.8
                        ? 'alerte' : 'normal'),
                // Last unpaid documents
                'unpaid_docs'     => \App\Models\DocumentHeader::where('third_partner_id', $c->id)
                    ->whereIn('document_type', ['InvoiceSale', 'DeliveryNote', 'TicketSale'])
                    ->whereIn('status', ['pending', 'partial', 'confirmed'])
                    ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
                    ->with('footer')
                    ->orderBy('issued_at')
                    ->get()
                    ->map(fn ($d) => [
                        'id'        => $d->id,
                        'reference' => $d->reference,
                        'type'      => $d->document_type,
                        'date'      => $d->issued_at?->format('d/m/Y'),
                        'total'     => (float) ($d->footer?->total_ttc ?? 0),
                        'due'       => (float) ($d->footer?->amount_due ?? 0),
                        'age_days'  => $d->issued_at ? now()->diffInDays($d->issued_at) : 0,
                    ]),
            ]);

        $totalEncours = $clients->sum('encours');
        $clientsAlerte = $clients->where('status', '!=', 'normal')->count();

        return response()->json([
            'total_encours'   => $totalEncours,
            'clients_count'   => $clients->count(),
            'clients_alerte'  => $clientsAlerte,
            'clients'         => $clients,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function parseDates(Request $request): array
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to'   => 'sometimes|date',
        ]);

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    private function getCompanyInfo(): array
    {
        return [
            'name'    => Setting::get('company', 'name', 'Mon Entreprise'),
            'address' => Setting::get('company', 'address', ''),
            'city'    => Setting::get('company', 'city', ''),
            'phone'   => Setting::get('company', 'phone', ''),
            'email'   => Setting::get('company', 'email', ''),
            'ice'     => Setting::get('company', 'ice', ''),
            'rc'      => Setting::get('company', 'rc', ''),
        ];
    }
}
