<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CreateTicketRequest;
use App\Models\DocumentHeader;
use App\Models\PosSession;
use App\Models\Setting;
use App\Services\PosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PosTicketController extends Controller
{
    public function __construct(
        private PosService $posService,
    ) {
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $session = PosSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->firstOrFail();

        $ticket = $this->posService->createTicket(
            $session,
            $request->validated('items'),
            $request->validated('payments'),
            $request->validated('customer_id'),
        );

        return response()->json($ticket, 201);
    }

    public function index(): JsonResponse
    {
        $session = PosSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return response()->json([]);
        }

        $tickets = DocumentHeader::where('pos_session_id', $session->id)
            ->where('document_type', 'TicketSale')
            ->with(['footer', 'payments', 'thirdPartner'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tickets);
    }

    public function void(DocumentHeader $ticket): JsonResponse
    {
        $ticket = $this->posService->voidTicket($ticket);

        return response()->json($ticket);
    }

    /**
     * Print a single ticket receipt (PDF for thermal printer — 80mm width).
     */
    public function print(DocumentHeader $ticket): Response
    {
        $ticket->load(['lignes', 'footer', 'payments', 'thirdPartner', 'user', 'warehouse']);

        $company = $this->getCompanyInfo();

        // Get terminal name from session
        $session = PosSession::with('terminal')->find($ticket->pos_session_id);
        $terminal = $session?->terminal?->name ?? '';

        $pdf = Pdf::loadView('pdf.ticket-receipt', [
            'ticket'   => $ticket,
            'company'  => $company,
            'terminal' => $terminal,
        ]);

        // 80mm receipt paper ≈ 226pts wide, variable height
        $pdf->setPaper([0, 0, 226, 800], 'portrait');
        $pdf->setOptions([
            'dpi'               => 96,
            'isPhpEnabled'      => true,
            'isRemoteEnabled'   => false,
            'defaultFont'       => 'DejaVu Sans',
        ]);

        return $pdf->download('ticket-' . $ticket->reference . '.pdf');
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
        ];
    }
}
