@php
    /**
     * Mise en page pilotee par le tenant (Parametres > Modeles de documents).
     *
     * $tpl arrive deja borne par DocumentTemplateService::harden() : couleurs
     * en hexa, enums fermees, entiers dans leurs bornes. Les textes libres
     * restent echappes ici ({{ }} ou e()) — ils ne sont jamais du HTML.
     */
    $fonts = [
        'dejavu'    => 'DejaVu Sans, sans-serif',
        'helvetica' => 'Helvetica, Arial, sans-serif',
        'times'     => '"Times New Roman", Times, serif',
        'courier'   => '"Courier New", Courier, monospace',
    ];

    $font     = $fonts[$tpl['font_family']] ?? $fonts['dejavu'];
    $accent   = $tpl['accent_color'];
    $ink      = $tpl['text_color'];
    $base     = (int) $tpl['font_size'];
    $small    = max(7, $base - 1);
    $tiny     = max(6, $base - 2);
    $currency = $tpl['currency'];
    $cols     = $tpl['columns'];

    // Largeurs relatives des colonnes actives, normalisees a 100 %.
    $weights = [
        'index'      => 5,
        'designation'=> 35,
        'quantity'   => 10,
        'unit_price' => 13,
        'discount'   => 10,
        'tax'        => 10,
        'total'      => 17,
    ];

    $active = ['designation' => $weights['designation']];
    foreach (['index', 'quantity', 'unit_price', 'discount', 'tax', 'total'] as $col) {
        if ($col === 'tax' && !$taxEnabled) continue;
        if (!empty($cols[$col])) $active[$col] = $weights[$col];
    }

    $sum   = array_sum($active) ?: 1;
    $width = fn (string $col) => isset($active[$col])
        ? 'width:' . round($active[$col] * 100 / $sum, 2) . '%;'
        : '';

    $money = fn ($amount) => number_format((float) $amount, $decimals, ',', ' ') . ' ' . $currency;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $typeLabel }} — {{ $doc->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: {{ $font }};
            font-size: {{ $base }}px;
            color: {{ $ink }};
            line-height: 1.5;
        }

        .page { padding: {{ (int) $tpl['margin_y'] }}px {{ (int) $tpl['margin_x'] }}px; position: relative; }

        /* ── Filigrane ──────────────────────────────────── */
        .watermark {
            position: fixed;
            top: 42%;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 72px;
            font-weight: bold;
            color: {{ $accent }};
            opacity: {{ round((int) $tpl['watermark_opacity'] / 100, 2) }};
            transform: rotate(-24deg);
            z-index: 0;
        }

        /* ── Header ─────────────────────────────────────── */
        .header { display: table; width: 100%; margin-bottom: 30px; }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-left { width: 55%; }
        .header-right { width: 45%; text-align: right; }
        .company-logo { max-height: {{ (int) $tpl['logo_height'] }}px; max-width: 180px; margin-bottom: 8px; }
        .company-name { font-size: {{ $base + 8 }}px; font-weight: bold; color: {{ $accent }}; margin-bottom: 4px; }
        .company-info { font-size: {{ $small }}px; color: #555; line-height: 1.6; }
        .doc-title { font-size: {{ $base + 12 }}px; font-weight: bold; color: {{ $accent }}; margin-bottom: 6px; }
        .doc-ref { font-size: {{ $base + 1 }}px; color: #666; }
        .header-note { font-size: {{ $small }}px; color: #555; margin-bottom: 18px; }

        /* ── Info boxes ─────────────────────────────────── */
        .info-row { display: table; width: 100%; margin-bottom: 20px; }
        .info-box { display: table-cell; vertical-align: top; padding-right: 15px; }
        .info-box:last-child { padding-right: 0; }
        .info-label { font-size: {{ $tiny }}px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 3px; }
        .info-value { font-size: {{ $base }}px; font-weight: 600; color: #333; }

        /* ── Partner box ────────────────────────────────── */
        .partner-box { border: 1px solid #ddd; border-radius: 4px; padding: 12px 15px; margin-bottom: 20px; }
        .partner-label { font-size: {{ $tiny }}px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 5px; }
        .partner-name { font-size: {{ $base + 3 }}px; font-weight: bold; color: {{ $ink }}; margin-bottom: 3px; }
        .partner-detail { font-size: {{ $small }}px; color: #555; }

        /* ── Lines table ────────────────────────────────── */
        .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .lines-table th {
            font-size: {{ $tiny }}px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 8px 10px;
            text-align: left;
        }
        .lines-table th.right { text-align: right; }
        .lines-table td { padding: 7px 10px; font-size: {{ $small }}px; }
        .lines-table td.right { text-align: right; }

        @if($tpl['table_style'] === 'filled')
        .lines-table th { background: {{ $accent }}; color: #ffffff; }
        .lines-table td { border-bottom: 1px solid #eee; }
        .lines-table tr:last-child td { border-bottom: 2px solid {{ $accent }}; }
        @elseif($tpl['table_style'] === 'bordered')
        .lines-table th { background: #f4f6f8; color: {{ $accent }}; border: 1px solid #ccc; }
        .lines-table td { border: 1px solid #ddd; }
        @else
        .lines-table th { color: {{ $accent }}; border-bottom: 2px solid {{ $accent }}; }
        .lines-table td { border-bottom: 1px solid #f0f0f0; }
        @endif

        @if($tpl['zebra_rows'])
        .lines-table tbody tr:nth-child(even) { background: #f9fafb; }
        @endif

        /* ── Totals ─────────────────────────────────────── */
        .totals-wrapper { display: table; width: 100%; margin-bottom: 25px; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals-box { display: table-cell; width: 45%; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 5px 10px; font-size: {{ $base }}px; }
        .totals-table td.label { color: #666; }
        .totals-table td.value { text-align: right; font-weight: 600; }
        .totals-table tr.grand-total { border-top: 2px solid {{ $accent }}; }
        .totals-table tr.grand-total td { font-size: {{ $base + 2 }}px; font-weight: bold; color: {{ $accent }}; padding-top: 8px; }

        /* ── Payments table ─────────────────────────────── */
        .payments-section { margin-bottom: 20px; }
        .payments-title { font-size: {{ $base }}px; font-weight: bold; color: {{ $accent }}; margin-bottom: 6px; border-bottom: 1px solid {{ $accent }}; padding-bottom: 4px; }
        .payments-table { width: 100%; border-collapse: collapse; }
        .payments-table th {
            background: #f0f4f8;
            color: {{ $accent }};
            font-size: {{ $tiny }}px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .payments-table th.right { text-align: right; }
        .payments-table td { padding: 5px 8px; font-size: {{ $small }}px; border-bottom: 1px solid #eee; }
        .payments-table td.right { text-align: right; }
        .payments-summary { margin-top: 6px; font-size: {{ $small }}px; padding: 6px 8px; background: #f9fafb; border-radius: 3px; }

        /* ── Notes / conditions ─────────────────────────── */
        .notes { border-top: 1px solid #eee; padding-top: 12px; margin-bottom: 15px; }
        .notes-label { font-size: {{ $tiny }}px; text-transform: uppercase; color: #999; margin-bottom: 3px; }
        .notes-text { font-size: {{ $small }}px; color: #555; }

        /* ── Signatures ─────────────────────────────────── */
        .signatures { display: table; width: 100%; margin-top: 30px; margin-bottom: 15px; }
        .signature-box { display: table-cell; width: 50%; padding-right: 20px; vertical-align: top; }
        .signature-box:last-child { padding-right: 0; padding-left: 20px; }
        .signature-label { font-size: {{ $small }}px; color: #555; margin-bottom: 4px; }
        .signature-line { border: 1px solid #ddd; border-radius: 4px; height: 70px; }

        /* ── Footer ─────────────────────────────────────── */
        .page-footer { border-top: 1px solid #ddd; padding-top: 10px; text-align: center; font-size: {{ $tiny }}px; color: #999; }
        .legal { font-size: {{ $tiny }}px; color: #888; margin-top: 5px; }
    </style>
</head>
<body>

@if($tpl['watermark_text'] !== '')
    <div class="watermark">{{ $tpl['watermark_text'] }}</div>
@endif

<div class="page">

    {{-- ── Header ──────────────────────────────────────────── --}}
    @php
        $companyBlock = $tpl['show_company_block'];
        $logoOnRight  = $tpl['logo_position'] === 'right';
        $showLogo     = $tpl['logo_position'] !== 'hidden' && !empty($company['logo']);
    @endphp
    <div class="header">
        <div class="header-left">
            @if($showLogo && !$logoOnRight)
                <img src="{{ $company['logo'] }}" alt="Logo" class="company-logo" />
            @endif
            @if($companyBlock)
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
            @endif
        </div>
        <div class="header-right">
            @if($showLogo && $logoOnRight)
                <img src="{{ $company['logo'] }}" alt="Logo" class="company-logo" />
            @endif
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

    {{-- ── Texte libre sous l'entête ───────────────────────── --}}
    @if($tpl['header_note'] !== '')
    <div class="header-note">{!! nl2br(e($tpl['header_note'])) !!}</div>
    @endif

    {{-- ── Partner ─────────────────────────────────────────── --}}
    @if($tpl['show_partner_block'] && $doc->thirdPartner)
    <div class="partner-box">
        <div class="partner-label">
            @if(in_array($doc->document_type, ['PurchaseOrder', 'ReceiptNotePurchase', 'InvoicePurchase', 'CreditNotePurchase', 'ReturnPurchase']))
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
    @php
        $infoBoxes = $tpl['show_status'] + ($tpl['show_warehouse'] && $doc->warehouse) + $tpl['show_user'];
    @endphp
    @if($infoBoxes > 0)
    <div class="info-row">
        @if($tpl['show_status'])
        <div class="info-box" style="width: {{ round(100 / $infoBoxes, 2) }}%;">
            <div class="info-label">Statut</div>
            <div class="info-value">{{ ucfirst($doc->status) }}</div>
        </div>
        @endif
        @if($tpl['show_warehouse'] && $doc->warehouse)
        <div class="info-box" style="width: {{ round(100 / $infoBoxes, 2) }}%;">
            <div class="info-label">Entrepôt</div>
            <div class="info-value">{{ $doc->warehouse->wh_title }}</div>
        </div>
        @endif
        @if($tpl['show_user'])
        <div class="info-box" style="width: {{ round(100 / $infoBoxes, 2) }}%;">
            <div class="info-label">Créé par</div>
            <div class="info-value">{{ $doc->user->name ?? '—' }}</div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Lines ───────────────────────────────────────────── --}}
    @if($doc->lignes->count())
    <table class="lines-table">
        <thead>
            <tr>
                @if($cols['index'])<th style="{{ $width('index') }}">#</th>@endif
                <th style="{{ $width('designation') }}">Désignation</th>
                @if($cols['quantity'])<th class="right" style="{{ $width('quantity') }}">Qté</th>@endif
                @if($cols['unit_price'])<th class="right" style="{{ $width('unit_price') }}">PU HT</th>@endif
                @if($cols['discount'])<th class="right" style="{{ $width('discount') }}">Rem %</th>@endif
                @if($taxEnabled && $cols['tax'])<th class="right" style="{{ $width('tax') }}">TVA %</th>@endif
                @if($cols['total'])<th class="right" style="{{ $width('total') }}">{{ $taxEnabled ? 'Total TTC' : 'Total HT' }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($doc->lignes as $i => $ligne)
            <tr>
                @if($cols['index'])<td>{{ $i + 1 }}</td>@endif
                <td>
                    {{ $ligne->designation }}
                    @if($cols['reference'] && $ligne->reference)
                        <br><span style="font-size:{{ $tiny }}px;color:#888;">Réf : {{ $ligne->reference }}</span>
                    @endif
                </td>
                @if($cols['quantity'])<td class="right">{{ number_format((float) $ligne->quantity, $decimals, ',', ' ') }}</td>@endif
                @if($cols['unit_price'])<td class="right">{{ number_format((float) $ligne->unit_price, $decimals, ',', ' ') }}</td>@endif
                @if($cols['discount'])<td class="right">{{ $ligne->discount_percent > 0 ? number_format((float) $ligne->discount_percent, $decimals, ',', ' ') . '%' : '—' }}</td>@endif
                @if($taxEnabled && $cols['tax'])<td class="right">{{ number_format((float) $ligne->tax_percent, 0) }}%</td>@endif
                @if($cols['total'])
                <td class="right">
                    {{-- total_ligne_ht est le vrai nom de colonne : `total_ht` n'existe
                         pas sur le modele et imprimait 0,00 quand la TVA est coupee. --}}
                    {{ number_format((float) ($taxEnabled ? $ligne->total_ttc : $ligne->total_ligne_ht), $decimals, ',', ' ') }}
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── Totals ──────────────────────────────────────────── --}}
    @if($tpl['show_totals'] && $doc->footer)
    <div class="totals-wrapper">
        <div class="totals-spacer"></div>
        <div class="totals-box">
            <table class="totals-table">
                @if($taxEnabled)
                <tr>
                    <td class="label">Total HT</td>
                    <td class="value">{{ $money($doc->footer->total_ht) }}</td>
                </tr>
                @endif
                @if($doc->footer->total_discount > 0)
                <tr>
                    <td class="label">Remise</td>
                    <td class="value">- {{ $money($doc->footer->total_discount) }}</td>
                </tr>
                @endif
                @if($taxEnabled)
                <tr>
                    <td class="label">TVA</td>
                    <td class="value">{{ $money($doc->footer->total_tax) }}</td>
                </tr>
                @endif
                <tr class="grand-total">
                    <td class="label">{{ $taxEnabled ? 'Total TTC' : 'Total' }}</td>
                    <td class="value">{{ $money($doc->footer->total_ttc) }}</td>
                </tr>
                @if($doc->payments->count())
                @php
                    $paidSum = $doc->payments->sum('amount');
                    $dueSum  = ($doc->footer->total_ttc ?? 0) - $paidSum;
                @endphp
                <tr>
                    <td class="label">Montant payé</td>
                    <td class="value">{{ $money($paidSum) }}</td>
                </tr>
                <tr>
                    <td class="label" style="font-weight:bold;">Reste à payer</td>
                    <td class="value" style="color: {{ $dueSum > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ $money($dueSum) }}
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>
    @endif

    @if($tpl['show_total_in_words'] && $doc->footer?->total_in_words)
    <div style="margin-bottom: 15px; font-size: {{ $small }}px; color: #555;">
        <strong>Arrêté la présente {{ mb_strtolower($typeLabel) }} à la somme de :</strong>
        {{ $doc->footer->total_in_words }}
    </div>
    @endif

    {{-- ── Payments ─────────────────────────────────────────── --}}
    @if($tpl['show_payments'] && $doc->payments->count())
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
                    <td class="right">{{ $money($payment->amount) }}</td>
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
            <strong>Total payé :</strong> {{ $money($totalPaid) }}
            @if($remaining > 0)
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong style="color: #dc2626;">Reste à payer : {{ $money($remaining) }}</strong>
            @else
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong style="color: #16a34a;">Entièrement payé</strong>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Notes ───────────────────────────────────────────── --}}
    @if($tpl['show_notes'] && $doc->notes)
    <div class="notes">
        <div class="notes-label">Notes</div>
        <div class="notes-text">{{ $doc->notes }}</div>
    </div>
    @endif

    {{-- ── Conditions du tenant ────────────────────────────── --}}
    @if($tpl['terms'] !== '')
    <div class="notes">
        <div class="notes-label">Conditions</div>
        <div class="notes-text">{!! nl2br(e($tpl['terms'])) !!}</div>
    </div>
    @endif

    {{-- ── Legal mentions ──────────────────────────────────── --}}
    @if($tpl['show_legal_mentions'] && $doc->footer?->legal_mentions)
    <div class="legal">{{ $doc->footer->legal_mentions }}</div>
    @endif

    {{-- ── Bank details ────────────────────────────────────── --}}
    @if($tpl['show_bank_details'] && $doc->footer?->bank_details && ($doc->payments->count() || in_array($doc->document_type, ['InvoiceSale', 'InvoicePurchase'])))
    <div style="margin-top: 15px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: {{ $small }}px;">
        <strong style="color: {{ $accent }};">Coordonnées bancaires</strong><br>
        {!! nl2br(e($doc->footer->bank_details)) !!}
    </div>
    @endif

    {{-- ── Signatures ──────────────────────────────────────── --}}
    @if($tpl['show_signature'])
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-label">{{ $tpl['signature_left_label'] }}</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <div class="signature-label">{{ $tpl['signature_right_label'] }}</div>
            <div class="signature-line"></div>
        </div>
    </div>
    @endif

    {{-- ── Page footer ─────────────────────────────────────── --}}
    <div class="page-footer">
        @if($tpl['footer_note'] !== '')
            {!! nl2br(e($tpl['footer_note'])) !!}
        @else
            {{ $company['name'] }}
            @if($company['phone']) — Tél : {{ $company['phone'] }} @endif
            @if($company['email']) — {{ $company['email'] }} @endif
        @endif
    </div>

</div>
</body>
</html>
