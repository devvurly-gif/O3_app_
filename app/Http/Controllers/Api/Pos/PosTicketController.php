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

        // POS history surfaces three kinds of documents scoped to the
        // current session:
        //   - TicketSale (cash tickets)
        //   - DeliveryNote (BL for en-compte credit sales)
        //   - ReturnSale (BR created from a retour)
        $tickets = DocumentHeader::where('pos_session_id', $session->id)
            ->whereIn('document_type', ['TicketSale', 'DeliveryNote', 'ReturnSale'])
            ->with(['footer', 'payments', 'thirdPartner'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tickets);
    }

    /**
     * Kept for backward compatibility — still points at voidTicket, which
     * only accepts TicketSale. New clients should call retour().
     */
    public function void(DocumentHeader $ticket): JsonResponse
    {
        $ticket = $this->posService->voidTicket($ticket);

        return response()->json($ticket);
    }

    /**
     * Process a POS return. Dispatches inside PosService: TicketSale is
     * reversed (stock back, payments dropped, status → cancelled); a
     * DeliveryNote spawns a linked ReturnSale (BR) that restores stock
     * and recalculates encours so the customer's credit is freed.
     */
    public function retour(DocumentHeader $document): JsonResponse
    {
        $document = $this->posService->returnTicket($document);

        return response()->json($document);
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
        // SECURITY: isPhpEnabled=false. Receipt templates do not use
        // `<script type="text/php">` (grep-verified), and enabling PHP
        // evaluation inside dompdf would let any admin-writable Setting
        // value (company name, address, footer…) trigger RCE as the
        // web user.
        $pdf->setOptions([
            'dpi'               => 96,
            'isPhpEnabled'      => false,
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
