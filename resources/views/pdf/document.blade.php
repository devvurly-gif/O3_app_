<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $typeLabel }} — {{ $doc->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.5; }

        .page { padding: 30px 40px; }

        /* ── Header ─────────────────────────────────────── */
        .header { display: table; width: 100%; margin-bottom: 30px; }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-left { width: 55%; }
        .header-right { width: 45%; text-align: right; }
        .company-name { font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 4px; }
        .company-info { font-size: 9px; color: #555; line-height: 1.6; }
        .doc-title { font-size: 22px; font-weight: bold; color: #1e3a5f; margin-bottom: 6px; }
        .doc-ref { font-size: 11px; color: #666; }

        /* ── Info boxes ─────────────────────────────────── */
        .info-row { display: table; width: 100%; margin-bottom: 20px; }
        .info-box { display: table-cell; width: 33.33%; vertical-align: top; padding-right: 15px; }
        .info-box:last-child { padding-right: 0; }
        .info-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 3px; }
        .info-value { font-size: 10px; font-weight: 600; color: #333; }

        /* ── Partner box ────────────────────────────────── */
        .partner-box { border: 1px solid #ddd; border-radius: 4px; padding: 12px 15px; margin-bottom: 20px; }
        .partner-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 5px; }
        .partner-name { font-size: 13px; font-weight: bold; color: #1a1a1a; margin-bottom: 3px; }
        .partner-detail { font-size: 9px; color: #555; }

        /* ── Lines table ────────────────────────────────── */
        .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .lines-table th {
            background: #1e3a5f;
            color: white;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 8px 10px;
            text-align: left;
        }
        .lines-table th.right { text-align: right; }
        .lines-table td { padding: 7px 10px; font-size: 9px; border-bottom: 1px solid #eee; }
        .lines-table td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .lines-table tr:nth-child(even) { background: #f9fafb; }
        .lines-table tr:last-child td { border-bottom: 2px solid #1e3a5f; }

        /* ── Totals ─────────────────────────────────────── */
        .totals-wrapper { display: table; width: 100%; margin-bottom: 25px; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals-box { display: table-cell; width: 45%; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 5px 10px; font-size: 10px; }
        .totals-table td.label { color: #666; }
        .totals-table td.value { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
        .totals-table tr.grand-total { border-top: 2px solid #1e3a5f; }
        .totals-table tr.grand-total td { font-size: 12px; font-weight: bold; color: #1e3a5f; padding-top: 8px; }

        /* ── Payments table ─────────────────────────────── */
        .payments-section { margin-bottom: 20px; }
        .payments-title { font-size: 10px; font-weight: bold; color: #1e3a5f; margin-bottom: 6px; border-bottom: 1px solid #1e3a5f; padding-bottom: 4px; }
        .payments-table { width: 100%; border-collapse: collapse; }
        .payments-table th {
            background: #f0f4f8;
            color: #1e3a5f;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .payments-table th.right { text-align: right; }
        .payments-table td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #eee; }
        .payments-table td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .payments-summary { margin-top: 6px; font-size: 9px; padding: 6px 8px; background: #f9fafb; border-radius: 3px; }

        /* ── Notes ──────────────────────────────────────── */
        .notes { border-top: 1px solid #eee; padding-top: 12px; margin-bottom: 15px; }
        .notes-label { font-size: 8px; text-transform: uppercase; color: #999; margin-bottom: 3px; }
        .notes-text { font-size: 9px; color: #555; }

        /* ── Footer ─────────────────────────────────────── */
        .page-footer { border-top: 1px solid #ddd; padding-top: 10px; text-align: center; font-size: 8px; color: #999; }
        .legal { font-size: 8px; color: #888; margin-top: 5px; }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Header ──────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-info">
                @if($company['address']){{ $company['address'] }}<br>@endif
                @if($company['city']){{ $company['city'] }}<br>@endif
                @if($company['phone'])Tél : {{ $company['phone'] }}<br>@endif
                @if($company['email']){{ $company['email'] }}<br>@endif
                @if($company['ice'])ICE : {{ $company['ice'] }}@endif
                @if($company['rc']) | RC : {{ $company['rc'] }}@endif
                @if($company['if']) | IF : {{ $company['if'] }}@endif
                @if($company['patente']) | Patente : {{ $company['patente'] }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">{{ $typeLabel }}</div>
            <div class="doc-ref">
                Réf : {{ $doc->reference ?? '—' }}<br>
                Date : {{ $doc->issued_at ? \Carbon\Carbon::parse($doc->issued_at)->format('d/m/Y') : '—' }}
                @if($doc->due_at)
                    <br>Échéance : {{ \Carbon\Carbon::parse($doc->due_at)->format('d/m/Y') }}
                @endif
            </div>
        </div>
    </div>

    {{-- ── Partner ─────────────────────────────────────────── --}}
    @if($doc->thirdPartner)
    <div class="partner-box">
        <div class="partner-label">
            @if(in_array($doc->document_type, ['PurchaseOrder', 'ReceiptNote', 'PurchaseInvoice']))
                Fournisseur
            @else
                Client
            @endif
        </div>
        <div class="partner-name">{{ $doc->thirdPartner->tp_title }}</div>
        <div class="partner-detail">
            @if($doc->thirdPartner->tp_address){{ $doc->thirdPartner->tp_address }}<br>@endif
            @if($doc->thirdPartner->tp_city){{ $doc->thirdPartner->tp_city }}<br>@endif
            @if($doc->thirdPartner->tp_phone)Tél : {{ $doc->thirdPartner->tp_phone }}<br>@endif
            @if($doc->thirdPartner->tp_email){{ $doc->thirdPartner->tp_email }}<br>@endif
            @if($doc->thirdPartner->tp_Ice_Number)ICE : {{ $doc->thirdPartner->tp_Ice_Number }}@endif
        </div>
    </div>
    @endif

    {{-- ── Info row ────────────────────────────────────────── --}}
    <div class="info-row">
        <div class="info-box">
            <div class="info-label">Statut</div>
            <div class="info-value">{{ ucfirst($doc->status) }}</div>
        </div>
        @if($doc->warehouse)
        <div class="info-box">
            <div class="info-label">Entrepôt</div>
            <div class="info-value">{{ $doc->warehouse->wh_title }}</div>
        </div>
        @endif
        <div class="info-box">
            <div class="info-label">Créé par</div>
            <div class="info-value">{{ $doc->user->name ?? '—' }}</div>
        </div>
    </div>

    {{-- ── Lines ───────────────────────────────────────────── --}}
    @if($doc->lignes->count())
    <table class="lines-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">Désignation</th>
                <th class="right" style="width: 10%;">Qté</th>
                <th class="right" style="width: 12%;">PU HT</th>
                <th class="right" style="width: 10%;">Rem %</th>
                <th class="right" style="width: 10%;">TVA %</th>
                <th class="right" style="width: 18%;">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($doc->lignes as $i => $ligne)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $ligne->designation }}
                    @if($ligne->reference)
                        <br><span style="font-size:8px;color:#888;">Réf : {{ $ligne->reference }}</span>
                    @endif
                </td>
                <td class="right">{{ number_format($ligne->quantity, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($ligne->unit_price, 2, ',', ' ') }}</td>
                <td class="right">{{ $ligne->discount_percent > 0 ? number_format($ligne->discount_percent, 2, ',', ' ') . '%' : '—' }}</td>
                <td class="right">{{ number_format($ligne->tax_percent, 0) }}%</td>
                <td class="right">{{ number_format($ligne->total_ttc, 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── Totals ──────────────────────────────────────────── --}}
    @if($doc->footer)
    <div class="totals-wrapper">
        <div class="totals-spacer"></div>
        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td class="label">Total HT</td>
                    <td class="value">{{ number_format($doc->footer->total_ht, 2, ',', ' ') }} MAD</td>
                </tr>
                @if($doc->footer->total_discount > 0)
                <tr>
                    <td class="label">Remise</td>
                    <td class="value">- {{ number_format($doc->footer->total_discount, 2, ',', ' ') }} MAD</td>
                </tr>
                @endif
                <tr>
                    <td class="label">TVA</td>
                    <td class="value">{{ number_format($doc->footer->total_tax, 2, ',', ' ') }} MAD</td>
                </tr>
                <tr class="grand-total">
                    <td class="label">Total TTC</td>
                    <td class="value">{{ number_format($doc->footer->total_ttc, 2, ',', ' ') }} MAD</td>
                </tr>
                @if($doc->payments->count())
                @php
                    $paidSum = $doc->payments->sum('amount');
                    $dueSum  = ($doc->footer->total_ttc ?? 0) - $paidSum;
                @endphp
                <tr>
                    <td class="label">Montant payé</td>
                    <td class="value">{{ number_format($paidSum, 2, ',', ' ') }} MAD</td>
                </tr>
                <tr>
                    <td class="label" style="font-weight:bold;">Reste à payer</td>
                    <td class="value" style="color: {{ $dueSum > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ number_format($dueSum, 2, ',', ' ') }} MAD
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @if($doc->footer->total_in_words)
    <div style="margin-bottom: 15px; font-size: 9px; color: #555;">
        <strong>Arrêté la présente {{ strtolower($typeLabel) }} à la somme de :</strong>
        {{ $doc->footer->total_in_words }}
    </div>
    @endif
    @endif

    {{-- ── Payments ─────────────────────────────────────────── --}}
    @if($doc->payments->count())
    <div class="payments-section">
        <div class="payments-title">Historique des paiements</div>
        <table class="payments-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Code</th>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 15%;">Mode</th>
                    <th style="width: 25%;">Référence</th>
                    <th class="right" style="width: 15%;">Montant</th>
                    <th style="width: 15%;">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($doc->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_code ?? '—' }}</td>
                    <td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y') : '—' }}</td>
                    <td>{{ ucfirst($payment->method) }}</td>
                    <td>{{ $payment->reference ?: '—' }}</td>
                    <td class="right">{{ number_format($payment->amount, 2, ',', ' ') }} MAD</td>
                    <td>{{ $payment->notes ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $totalPaid = $doc->payments->sum('amount');
            $totalTtc  = $doc->footer?->total_ttc ?? 0;
            $remaining = $totalTtc - $totalPaid;
        @endphp
        <div class="payments-summary">
            <strong>Total payé :</strong> {{ number_format($totalPaid, 2, ',', ' ') }} MAD
            @if($remaining > 0)
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong style="color: #dc2626;">Reste à payer : {{ number_format($remaining, 2, ',', ' ') }} MAD</strong>
            @else
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong style="color: #16a34a;">Entièrement payé</strong>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Notes ───────────────────────────────────────────── --}}
    @if($doc->notes)
    <div class="notes">
        <div class="notes-label">Notes</div>
        <div class="notes-text">{{ $doc->notes }}</div>
    </div>
    @endif

    {{-- ── Legal mentions ──────────────────────────────────── --}}
    @if($doc->footer?->legal_mentions)
    <div class="legal">{{ $doc->footer->legal_mentions }}</div>
    @endif

    {{-- ── Bank details ────────────────────────────────────── --}}
    @if($doc->footer?->bank_details && ($doc->payments->count() || in_array($doc->document_type, ['Invoice', 'PurchaseInvoice'])))
    <div style="margin-top: 15px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 9px;">
        <strong style="color: #1e3a5f;">Coordonnées bancaires</strong><br>
        {!! nl2br(e($doc->footer->bank_details)) !!}
    </div>
    @endif

    {{-- ── Page footer ─────────────────────────────────────── --}}
    <div class="page-footer">
        {{ $company['name'] }}
        @if($company['phone']) — Tél : {{ $company['phone'] }} @endif
        @if($company['email']) — {{ $company['email'] }} @endif
    </div>

</div>
</body>
</html>
