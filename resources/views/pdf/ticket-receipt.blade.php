<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $ticket->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, monospace;
            font-size: 9px;
            color: #000;
            width: 80mm;
            margin: 0 auto;
        }
        .receipt { padding: 5mm; }

        /* Header */
        .header { text-align: center; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px; }
        .company-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .company-info { font-size: 8px; color: #333; line-height: 1.4; }

        /* Ticket info */
        .ticket-info { margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #000; font-size: 9px; }
        .ticket-info .row { display: table; width: 100%; }
        .ticket-info .label { display: table-cell; width: 35%; color: #555; }
        .ticket-info .value { display: table-cell; width: 65%; text-align: right; font-weight: 600; }

        /* Client */
        .client-info { margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dashed #000; }
        .client-info .name { font-weight: bold; font-size: 10px; }
        .client-info .detail { font-size: 8px; color: #555; }

        /* Lines */
        .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .lines-table th { font-size: 8px; text-align: left; padding: 3px 2px; border-bottom: 1px solid #000; }
        .lines-table th.right { text-align: right; }
        .lines-table td { font-size: 9px; padding: 3px 2px; border-bottom: 1px dotted #ccc; }
        .lines-table td.right { text-align: right; font-variant-numeric: tabular-nums; }

        /* Totals */
        .totals { border-top: 1px dashed #000; padding-top: 6px; margin-bottom: 8px; }
        .totals .row { display: table; width: 100%; margin-bottom: 2px; }
        .totals .label { display: table-cell; width: 50%; font-size: 9px; }
        .totals .value { display: table-cell; width: 50%; text-align: right; font-size: 9px; font-weight: 600; }
        .totals .grand { font-size: 12px; font-weight: bold; border-top: 1px solid #000; padding-top: 4px; margin-top: 4px; }

        /* Payments */
        .payments { border-top: 1px dashed #000; padding-top: 6px; margin-bottom: 8px; }
        .payments-title { font-size: 9px; font-weight: bold; margin-bottom: 4px; }
        .payments .row { display: table; width: 100%; margin-bottom: 1px; }
        .payments .label { display: table-cell; width: 50%; font-size: 8px; }
        .payments .value { display: table-cell; width: 50%; text-align: right; font-size: 8px; }

        /* Footer */
        .footer { text-align: center; border-top: 1px dashed #000; padding-top: 8px; font-size: 8px; color: #555; }
        .footer .thanks { font-size: 10px; font-weight: bold; color: #000; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="receipt">

    {{-- ── Header ──────────────────────────────────── --}}
    <div class="header">
        <div class="company-name">{{ $company['name'] }}</div>
        <div class="company-info">
            @if($company['address']){{ $company['address'] }}<br>@endif
            @if($company['city']){{ $company['city'] }}<br>@endif
            @if($company['phone'])Tél: {{ $company['phone'] }}<br>@endif
            @if($company['ice'])ICE: {{ $company['ice'] }}@endif
        </div>
    </div>

    {{-- ── Ticket info ─────────────────────────────── --}}
    <div class="ticket-info">
        <div class="row">
            <span class="label">Ticket</span>
            <span class="value">{{ $ticket->reference }}</span>
        </div>
        <div class="row">
            <span class="label">Date</span>
            <span class="value">{{ \Carbon\Carbon::parse($ticket->issued_at)->format('d/m/Y H:i') }}</span>
        </div>
        <div class="row">
            <span class="label">Caissier</span>
            <span class="value">{{ $ticket->user->name ?? '—' }}</span>
        </div>
        @if($ticket->warehouse)
        <div class="row">
            <span class="label">Terminal</span>
            <span class="value">{{ $terminal ?? '—' }}</span>
        </div>
        @endif
    </div>

    {{-- ── Client ──────────────────────────────────── --}}
    @if($ticket->thirdPartner && $ticket->thirdPartner->tp_code !== 'CLIENT-COMPTOIR')
    <div class="client-info">
        <div class="name">{{ $ticket->thirdPartner->tp_title }}</div>
        @if($ticket->thirdPartner->tp_phone)
            <div class="detail">Tél: {{ $ticket->thirdPartner->tp_phone }}</div>
        @endif
        @if($ticket->thirdPartner->type_compte === 'en_compte')
            <div class="detail" style="font-weight:bold; color:#c00;">Client En Compte</div>
        @endif
    </div>
    @endif

    {{-- ── Lines ───────────────────────────────────── --}}
    <table class="lines-table">
        <thead>
            <tr>
                <th>Article</th>
                <th class="right">Qté</th>
                <th class="right">PU</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ticket->lignes as $ligne)
            @php
                $lineHt = $ligne->quantity * $ligne->unit_price * (1 - ($ligne->discount_percent ?? 0) / 100);
                $lineTtc = $lineHt * (1 + ($ligne->tax_percent ?? 0) / 100);
            @endphp
            <tr>
                <td>{{ $ligne->designation }}</td>
                <td class="right">{{ intval($ligne->quantity) }}</td>
                <td class="right">{{ number_format($ligne->unit_price, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($lineTtc, 2, ',', ' ') }}</td>
            </tr>
            @if($ligne->discount_percent > 0)
            <tr>
                <td colspan="4" style="font-size:7px; color:#888; padding-left:8px;">Remise: {{ number_format($ligne->discount_percent, 0) }}%</td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    {{-- ── Totals ──────────────────────────────────── --}}
    @if($ticket->footer)
    <div class="totals">
        <div class="row">
            <span class="label">Sous-total HT</span>
            <span class="value">{{ number_format($ticket->footer->total_ht, 2, ',', ' ') }}</span>
        </div>
        @if($ticket->footer->total_tax > 0)
        <div class="row">
            <span class="label">TVA</span>
            <span class="value">{{ number_format($ticket->footer->total_tax, 2, ',', ' ') }}</span>
        </div>
        @endif
        <div class="row grand">
            <span class="label">TOTAL TTC</span>
            <span class="value">{{ number_format($ticket->footer->total_ttc, 2, ',', ' ') }} MAD</span>
        </div>
        @if($ticket->footer->amount_due > 0)
        <div class="row" style="margin-top:4px;">
            <span class="label">Payé</span>
            <span class="value">{{ number_format($ticket->footer->amount_paid, 2, ',', ' ') }} MAD</span>
        </div>
        <div class="row" style="font-weight:bold; color:#c00;">
            <span class="label">Reste (En Compte)</span>
            <span class="value">{{ number_format($ticket->footer->amount_due, 2, ',', ' ') }} MAD</span>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Payments ────────────────────────────────── --}}
    @if($ticket->payments->count())
    <div class="payments">
        <div class="payments-title">Mode(s) de paiement</div>
        @php
            $methodLabels = [
                'cash' => 'Espèces',
                'card' => 'Carte bancaire',
                'credit' => 'En Compte',
                'cheque' => 'Chèque',
                'bank_transfer' => 'Virement',
                'effet' => 'Effet',
            ];
        @endphp
        @foreach($ticket->payments as $payment)
        <div class="row">
            <span class="label">{{ $methodLabels[$payment->method] ?? ucfirst($payment->method) }}</span>
            <span class="value">{{ number_format($payment->amount, 2, ',', ' ') }} MAD</span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Footer ──────────────────────────────────── --}}
    <div class="footer">
        <div class="thanks">Merci pour votre achat !</div>
        <div>{{ $company['name'] }}</div>
        @if($company['phone'])<div>Tél: {{ $company['phone'] }}</div>@endif
        <div style="margin-top: 4px; font-size: 7px;">{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

</div>
</body>
</html>
