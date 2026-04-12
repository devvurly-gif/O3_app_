<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseSessionRequest;
use App\Http\Requests\Pos\OpenSessionRequest;
use App\Mail\SessionClosingReportMail;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\PosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class PosSessionController extends Controller
{
    public function __construct(
        private PosService $posService,
    ) {
    }

    /**
     * List all POS sessions (admin/manager view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = PosSession::with(['terminal', 'user:id,name'])
            ->latest('opened_at');

        // Filter by status
        if ($request->query('status') === 'open') {
            $query->whereNull('closed_at');
        } elseif ($request->query('status') === 'closed') {
            $query->whereNotNull('closed_at');
        }

        // Filter by terminal
        if ($request->query('terminal_id')) {
            $query->where('pos_terminal_id', $request->query('terminal_id'));
        }

        $sessions = $query->paginate($request->query('per_page', 20));

        return response()->json($sessions);
    }

    /**
     * Force-close a session (admin/manager).
     */
    public function forceClose(PosSession $session): JsonResponse
    {
        if ($session->closed_at) {
            return response()->json(['message' => 'Session déjà fermée.'], 422);
        }

        $session = $this->posService->closeSession($session, $session->opening_cash, 'Fermeture forcée par admin');

        return response()->json($session->load('terminal'));
    }

    public function open(OpenSessionRequest $request): JsonResponse
    {
        $session = $this->posService->openSession(
            $request->validated('pos_terminal_id'),
            auth()->id(),
            $request->validated('opening_cash'),
        );

        return response()->json($session->load('terminal.warehouse'), 201);
    }

    public function close(CloseSessionRequest $request, PosSession $session): JsonResponse
    {
        $session = $this->posService->closeSession(
            $session,
            $request->validated('closing_cash'),
            $request->validated('notes'),
        );

        $session->load(['terminal', 'user']);

        // Generate session closing report and send email to admins
        try {
            $stats = $this->buildSessionStats($session);
            $this->sendClosingReportEmail($session, $stats);
        } catch (\Throwable $e) {
            // Don't fail the close operation if email fails
            \Log::error('Failed to send session closing report email: ' . $e->getMessage());
        }

        return response()->json($session);
    }

    public function current(): JsonResponse
    {
        $session = PosSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->with(['terminal.warehouse'])
            ->first();

        if (!$session) {
            return response()->json(null, 204);
        }
        return response()->json($session);
    }

    /**
     * Download the session closing report as PDF.
     */
    public function closingReport(PosSession $session): Response
    {
        if (!$session->closed_at) {
            abort(422, 'La session n\'est pas encore fermée.');
        }

        $session->load(['terminal', 'user']);
        $stats = $this->buildSessionStats($session);
        $tickets = $this->getSessionTickets($session);
        $company = $this->getCompanyInfo();

        $pdf = Pdf::loadView('pdf.session-closing-report', [
            'session'  => $session,
            'stats'    => $stats,
            'tickets'  => $tickets,
            'company'  => $company,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('rapport-fermeture-session-' . $session->id . '.pdf');
    }

    /**
     * Build statistics for a closed session.
     */
    private function buildSessionStats(PosSession $session): array
    {
        $tickets = $this->getSessionTickets($session);

        $activeTickets = $tickets->where('status', '!=', 'cancelled');

        // Sum payments by method
        $paymentsByMethod = [];
        foreach ($activeTickets as $t) {
            foreach ($t->payments as $p) {
                $method = $p->method;
                $paymentsByMethod[$method] = ($paymentsByMethod[$method] ?? 0) + $p->amount;
            }
        }

        $totalPaid = array_sum($paymentsByMethod);
        $totalCredit = $paymentsByMethod['credit'] ?? 0;

        return [
            'total_tickets'      => $activeTickets->count(),
            'cancelled_tickets'  => $tickets->where('status', 'cancelled')->count(),
            'total_ttc'          => $activeTickets->sum(fn ($t) => $t->footer?->total_ttc ?? 0),
            'total_ht'           => $activeTickets->sum(fn ($t) => $t->footer?->total_ht ?? 0),
            'total_tax'          => $activeTickets->sum(fn ($t) => $t->footer?->total_tax ?? 0),
            'total_paid'         => $totalPaid,
            'total_credit'       => $totalCredit,
            'payments_by_method' => $paymentsByMethod,
        ];
    }

    /**
     * Get all tickets for a session.
     */
    private function getSessionTickets(PosSession $session)
    {
        return DocumentHeader::where('pos_session_id', $session->id)
            ->where('document_type', 'TicketSale')
            ->with(['footer', 'payments', 'thirdPartner'])
            ->orderBy('issued_at')
            ->get();
    }

    /**
     * Send closing report email to all admin users.
     */
    private function sendClosingReportEmail(PosSession $session, array $stats): void
    {
        $tickets = $this->getSessionTickets($session);
        $company = $this->getCompanyInfo();

        // Generate PDF
        $pdf = Pdf::loadView('pdf.session-closing-report', [
            'session'  => $session,
            'stats'    => $stats,
            'tickets'  => $tickets,
            'company'  => $company,
        ]);
        $pdf->setPaper('A4', 'portrait');
        $pdfContent = $pdf->output();

        // Find admin users with email
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->whereNotNull('email')
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(
                new SessionClosingReportMail($session, $stats, $pdfContent)
            );
        }
    }

    /**
     * GET /api/pos/report/daily
     * Consolidated daily POS report (all sessions for a given date).
     */
    public function dailyReport(Request $request): JsonResponse|Response
    {
        $date = $request->input('date', now()->toDateString());
        $format = $request->input('format', 'json'); // json or pdf

        $sessions = PosSession::whereNotNull('closed_at')
            ->whereDate('opened_at', $date)
            ->with(['terminal', 'user'])
            ->orderBy('opened_at')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(['message' => 'Aucune session fermée pour cette date.', 'data' => null], 200);
        }

        // Aggregate stats across all sessions
        $allTickets = DocumentHeader::where('document_type', 'TicketSale')
            ->whereIn('pos_session_id', $sessions->pluck('id'))
            ->with(['footer', 'payments', 'thirdPartner'])
            ->orderBy('issued_at')
            ->get();

        $activeTickets = $allTickets->where('status', '!=', 'cancelled');

        $paymentsByMethod = [];
        foreach ($activeTickets as $t) {
            foreach ($t->payments as $p) {
                $paymentsByMethod[$p->method] = ($paymentsByMethod[$p->method] ?? 0) + (float) $p->amount;
            }
        }

        $totalPaid = array_sum($paymentsByMethod);
        $totalCredit = $paymentsByMethod['credit'] ?? 0;

        // Per-session summary
        $sessionsSummary = $sessions->map(function ($s) {
            $stats = $this->buildSessionStats($s);
            return [
                'id'             => $s->id,
                'terminal'       => $s->terminal->name ?? '—',
                'user'           => $s->user->name ?? '—',
                'opened_at'      => $s->opened_at,
                'closed_at'      => $s->closed_at,
                'opening_cash'   => (float) $s->opening_cash,
                'closing_cash'   => (float) $s->closing_cash,
                'cash_difference'=> (float) $s->cash_difference,
                'tickets'        => $stats['total_tickets'],
                'total_ttc'      => $stats['total_ttc'],
            ];
        });

        $data = [
            'date'               => $date,
            'sessions_count'     => $sessions->count(),
            'total_tickets'      => $activeTickets->count(),
            'cancelled_tickets'  => $allTickets->where('status', 'cancelled')->count(),
            'total_ttc'          => $activeTickets->sum(fn ($t) => $t->footer?->total_ttc ?? 0),
            'total_ht'           => $activeTickets->sum(fn ($t) => $t->footer?->total_ht ?? 0),
            'total_tax'          => $activeTickets->sum(fn ($t) => $t->footer?->total_tax ?? 0),
            'total_paid'         => $totalPaid,
            'total_credit'       => $totalCredit,
            'payments_by_method' => $paymentsByMethod,
            'total_opening_cash' => $sessions->sum('opening_cash'),
            'total_closing_cash' => $sessions->sum('closing_cash'),
            'total_difference'   => $sessions->sum('cash_difference'),
            'sessions'           => $sessionsSummary,
        ];

        if ($format === 'pdf') {
            $company = $this->getCompanyInfo();
            $pdf = Pdf::loadView('pdf.pos-daily-report', [
                'data'    => $data,
                'company' => $company,
                'tickets' => $allTickets,
            ]);
            $pdf->setPaper('A4', 'portrait');
            return $pdf->download("rapport-pos-journalier-{$date}.pdf");
        }

        return response()->json($data);
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
