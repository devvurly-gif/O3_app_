{{--
    Facture d'abonnement O3App.

    Mise en page volontairement sobre et autonome : dompdf ne charge ni Tailwind
    ni police externe, tout est en CSS inline. Les mentions legales marocaines
    (ICE des deux parties, RC, IF, patente, montant en toutes lettres) sont
    obligatoires — ne pas les retirer pour gagner de la place.
--}}
@php
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', ' ') . ' MAD';
    $periodLabel = $invoice->billing_period === 'yearly' ? 'Annuel' : 'Mensuel';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.45; }
        .head { width: 100%; margin-bottom: 22px; }
        .head td { vertical-align: top; }
        .issuer-name { font-size: 16px; font-weight: bold; color: #1F4E79; }
        .doc-title { font-size: 22px; font-weight: bold; color: #1F4E79; text-align: right; }
        .doc-meta { text-align: right; margin-top: 6px; font-size: 10px; }
        .muted { color: #6b7280; }
        .parties { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .parties td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #e5e7eb; }
        .parties .label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 4px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.lines th { background: #1F4E79; color: #fff; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; padding: 7px 8px; text-align: left; }
        table.lines td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        table.lines .num { text-align: right; white-space: nowrap; }
        .totals { width: 44%; margin-left: 56%; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .totals .k { color: #6b7280; }
        .totals .v { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 2px solid #1F4E79; font-weight: bold; font-size: 12px; padding-top: 8px; }
        .words { margin-top: 14px; padding: 9px 12px; background: #f9fafb; border-left: 3px solid #1F4E79; }
        .pay { margin-top: 18px; padding: 10px 12px; border: 1px solid #e5e7eb; }
        .legal { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8.5px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

<table class="head">
    <tr>
        <td>
            <div class="issuer-name">{{ $issuer['name'] ?? '' }}</div>
            @if (!empty($issuer['legal_form']))
                <div class="muted">{{ $issuer['legal_form'] }}</div>
            @endif
            <div>{{ $issuer['address'] ?? '' }}</div>
            <div>{{ $issuer['city'] ?? '' }}</div>
            @if (!empty($issuer['phone']))<div>Tél. {{ $issuer['phone'] }}</div>@endif
            @if (!empty($issuer['email']))<div>{{ $issuer['email'] }}</div>@endif
        </td>
        <td>
            <div class="doc-title">FACTURE</div>
            <div class="doc-meta">
                <div><strong>N° {{ $invoice->number }}</strong></div>
                <div>Date : {{ $invoice->issued_at?->format('d/m/Y') }}</div>
                <div>Échéance : {{ $invoice->due_at?->format('d/m/Y') }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td>
            <div class="label">Prestataire</div>
            <div><strong>{{ $issuer['name'] ?? '' }}</strong></div>
            @if (!empty($issuer['ice']))<div>ICE : {{ $issuer['ice'] }}</div>@endif
            @if (!empty($issuer['rc']))<div>RC : {{ $issuer['rc'] }}</div>@endif
            @if (!empty($issuer['if']))<div>IF : {{ $issuer['if'] }}</div>@endif
            @if (!empty($issuer['patente']))<div>Patente : {{ $issuer['patente'] }}</div>@endif
        </td>
        <td>
            <div class="label">Client</div>
            <div><strong>{{ $client['name'] ?? '' }}</strong></div>
            @if (!empty($client['address']))<div>{{ $client['address'] }}</div>@endif
            @if (!empty($client['ice']))
                <div>ICE : {{ $client['ice'] }}</div>
            @else
                <div class="muted">ICE : non communiqué</div>
            @endif
            @if (!empty($client['email']))<div>{{ $client['email'] }}</div>@endif
            @if (!empty($client['domain']))<div class="muted">{{ $client['domain'] }}</div>@endif
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th>Désignation</th>
            <th style="width: 92px;">Période</th>
            <th class="num" style="width: 95px;">Montant HT</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <strong>Abonnement O3 App — formule {{ $plan['name'] ?? $invoice->plan }}</strong>
                <div class="muted">
                    {{ $periodLabel }}, service en ligne de gestion commerciale
                    @if (!empty($plan['limits']['users'])) — {{ $plan['limits']['users'] }} utilisateurs inclus @endif
                </div>
            </td>
            <td>
                du {{ $invoice->period_starts_at?->format('d/m/Y') }}<br>
                au {{ $invoice->period_ends_at?->format('d/m/Y') }}
            </td>
            <td class="num">{{ $money($invoice->subtotal_cents) }}</td>
        </tr>

        @if ($invoice->setup_fee_cents > 0)
            <tr>
                <td>
                    <strong>Frais de mise en service</strong>
                    <div class="muted">Paramétrage, import du fichier articles et tiers, formation à distance</div>
                </td>
                <td class="muted">Unique</td>
                <td class="num">{{ $money($invoice->setup_fee_cents) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="k">Total HT</td>
        <td class="v">{{ $money($invoice->amount_ht_cents) }}</td>
    </tr>
    @if ($invoice->vat_cents > 0)
        <tr>
            <td class="k">TVA {{ rtrim(rtrim(number_format((float) $invoice->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
            <td class="v">{{ $money($invoice->vat_cents) }}</td>
        </tr>
    @else
        <tr>
            <td class="k" colspan="2">{{ config('billing.vat_exemption_note') }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td>Total TTC</td>
        <td class="v">{{ $money($invoice->amount_ttc_cents) }}</td>
    </tr>
</table>

<div class="words">
    Arrêtée la présente facture à la somme de <strong>{{ $amountInWords }}</strong>.
</div>

<div class="pay">
    <strong>Règlement</strong> — à réception, au plus tard le {{ $invoice->due_at?->format('d/m/Y') }}.
    @if (!empty($issuer['iban']))
        <br>Virement : {{ $issuer['bank'] ?? '' }} — IBAN {{ $issuer['iban'] }}
    @endif
    <br><span class="muted">Merci de rappeler le numéro {{ $invoice->number }} sur votre ordre de virement ou votre chèque.</span>
    @if ($invoice->note)
        <br><span class="muted">{{ $invoice->note }}</span>
    @endif
</div>

<div class="legal">
    {{ $issuer['name'] ?? '' }}@if (!empty($issuer['legal_form'])) — {{ $issuer['legal_form'] }}@endif
    @if (!empty($issuer['address'])) — {{ $issuer['address'] }}, {{ $issuer['city'] ?? '' }}@endif
    <br>
    @if (!empty($issuer['ice']))ICE {{ $issuer['ice'] }}@endif
    @if (!empty($issuer['rc'])) — RC {{ $issuer['rc'] }}@endif
    @if (!empty($issuer['if'])) — IF {{ $issuer['if'] }}@endif
    @if (!empty($issuer['patente'])) — Patente {{ $issuer['patente'] }}@endif
    @if (!empty($issuer['cnss'])) — CNSS {{ $issuer['cnss'] }}@endif
    @if (!empty($issuer['website']))<br>{{ $issuer['website'] }}@endif
</div>

</body>
</html>
