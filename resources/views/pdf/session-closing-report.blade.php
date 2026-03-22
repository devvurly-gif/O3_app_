<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de fermeture — Session #{{ $session->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }

        .header { display: table; width: 100%; margin-bottom: 25px; }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-left { width: 55%; }
        .header-right { width: 45%; text-align: right; }
        .company-name { font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 4px; }
        .company-info { font-size: 9px; color: #555; line-height: 1.6; }
        .doc-title { font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 6px; }
        .doc-ref { font-size: 10px; color: #666; }

        .kpi-row { display: table; width: 100%; margin-bottom: 20px; }
        .kpi-box { display: table-cell; width: 25%; padding: 8px; vertical-align: top; }
        .kpi-card { border: 1px solid #ddd; border-radius: 4px; padding: 10px; text-align: center; }
        .kpi-label { font-size: 8px; text-transform: uppercase; color: #999; margin-bottom: 4px; }
        .kpi-value { font-size: 16px; font-weight: bold; color: #1e3a5f; }
        .kpi-value.green { color: #16a34a; }
        .kpi-value.red { color: #dc2626; }

        .section-title { font-size: 12px; font-weight: bold; color: #1e3a5f; margin-bottom: 8px; border-bottom: 2px solid #1e3a5f; padding-bottom: 4px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px 10px; font-size: 10px; }
        .info-table td.label { color: #666; width: 40%; }
        .info-table td.value { font-weight: 600; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th {
            background: #1e3a5f; color: white; font-size: 8px;
            text-transform: uppercase; letter-spacing: 0.3px;
            padding: 7px 8px; text-align: left;
        }
        .data-table th.right { text-align: right; }
        .data-table td { padding: 6px 8px; font-size: 9px; border-bottom: 1px solid #eee; }
        .data-table td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .data-table tr:nth-child(even) { background: #f9fafb; }
        .data-table tr.cancelled td { color: #999; text-decoration: line-through; }

        .summary-table { width: 60%; margin-left: auto; border-collapse: collapse; margin-bottom: 20px; }
        .summary-table td { padding: 5px 10px; font-size: 10px; }
        .summary-table td.label { color: #666; }
        .summary-table td.value { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
        .summary-table tr.total { border-top: 2px solid #1e3a5f; }
        .summary-table tr.total td { font-size: 12px; font-weight: bold; color: #1e3a5f; padding-top: 8px; }

        .cash-box { border: 2px solid #1e3a5f; border-radius: 4px; padding: 12px; margin-bottom: 20px; }
        .cash-row { display: table; width: 100%; margin-bottom: 4px; }
        .cash-label { display: table-cell; width: 50%; font-size: 10px; color: #555; }
        .cash-value { display: table-cell; width: 50%; text-align: right; font-size: 11px; font-weight: 600; }
        .cash-diff { font-size: 14px; font-weight: bold; }

        .page-footer { border-top: 1px solid #ddd; padding-top: 10px; text-align: center; font-size: 8px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Header ──────────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-info">
                @if($company['address']){{ $company['address'] }}<br>@endif
                @if($company['city']){{ $company['city'] }}<br>@endif
                @if($company['phone'])Tél : {{ $company['phone'] }}<br>@endif
                @if($company['ice'])ICE : {{ $company['ice'] }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">Rapport de Fermeture</div>
            <div class="doc-ref">
                Session #{{ $session->id }}<br>
                Terminal : {{ $session->terminal->name ?? '—' }}<br>
                Caissier : {{ $session->user->name ?? '—' }}<br>
                {{ \Carbon\Carbon::parse($session->opened_at)->format('d/m/Y H:i') }}
                — {{ \Carbon\Carbon::parse($session->closed_at)->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    {{-- ── KPIs ────────────────────────────────────── --}}
    <div class="kpi-row">
        <div class="kpi-box">
            <div class="kpi-card">
                <div class="kpi-label">Tickets</div>
                <div class="kpi-value">{{ $stats['total_tickets'] }}</div>
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-card">
                <div class="kpi-label">CA TTC</div>
                <div class="kpi-value">{{ number_format($stats['total_ttc'], 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-card">
                <div class="kpi-label">Annulés</div>
                <div class="kpi-value red">{{ $stats['cancelled_tickets'] }}</div>
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-card">
                <div class="kpi-label">Différence Caisse</div>
                <div class="kpi-value {{ $session->cash_difference >= 0 ? 'green' : 'red' }}">
                    {{ $session->cash_difference >= 0 ? '+' : '' }}{{ number_format($session->cash_difference, 2, ',', ' ') }}
                </div>
            </div>
        </div>
    </div>

    {{-- ── Cash reconciliation ─────────────────────── --}}
    <div class="section-title">Réconciliation Caisse</div>
    <div class="cash-box">
        <div class="cash-row">
            <span class="cash-label">Fond de caisse ouverture</span>
            <span class="cash-value">{{ number_format($session->opening_cash, 2, ',', ' ') }} MAD</span>
        </div>
        <div class="cash-row">
            <span class="cash-label">Encaissements espèces</span>
            <span class="cash-value">{{ number_format($stats['payments_by_method']['cash'] ?? 0, 2, ',', ' ') }} MAD</span>
        </div>
        <div class="cash-row" style="border-top:1px solid #ccc; padding-top:4px; margin-top:4px;">
            <span class="cash-label" style="font-weight:bold;">Espèces attendues</span>
            <span class="cash-value">{{ number_format($session->expected_cash, 2, ',', ' ') }} MAD</span>
        </div>
        <div class="cash-row">
            <span class="cash-label" style="font-weight:bold;">Espèces comptées</span>
            <span class="cash-value">{{ number_format($session->closing_cash, 2, ',', ' ') }} MAD</span>
        </div>
        <div class="cash-row" style="border-top:2px solid #1e3a5f; padding-top:6px; margin-top:6px;">
            <span class="cash-label" style="font-weight:bold; font-size:12px;">Différence</span>
            <span class="cash-value cash-diff" style="color:{{ $session->cash_difference >= 0 ? '#16a34a' : '#dc2626' }};">
                {{ $session->cash_difference >= 0 ? '+' : '' }}{{ number_format($session->cash_difference, 2, ',', ' ') }} MAD
            </span>
        </div>
    </div>

    {{-- ── Payment Summary ─────────────────────────── --}}
    <div class="section-title">Résumé des Paiements</div>
    <table class="summary-table">
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
        @foreach($stats['payments_by_method'] as $method => $amount)
        <tr>
            <td class="label">{{ $methodLabels[$method] ?? ucfirst($method) }}</td>
            <td class="value">{{ number_format($amount, 2, ',', ' ') }} MAD</td>
        </tr>
        @endforeach
        <tr class="total">
            <td class="label">Total encaissé</td>
            <td class="value">{{ number_format($stats['total_paid'], 2, ',', ' ') }} MAD</td>
        </tr>
        @if($stats['total_credit'] > 0)
        <tr>
            <td class="label" style="color:#dc2626; font-weight:bold;">Total En Compte</td>
            <td class="value" style="color:#dc2626;">{{ number_format($stats['total_credit'], 2, ',', ' ') }} MAD</td>
        </tr>
        @endif
    </table>

    {{-- ── Tickets list ────────────────────────────── --}}
    <div class="section-title">Liste des Tickets ({{ count($tickets) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:18%;">Référence</th>
                <th style="width:12%;">Heure</th>
                <th style="width:22%;">Client</th>
                <th style="width:12%;">Statut</th>
                <th style="width:13%;">Paiement</th>
                <th class="right" style="width:18%;">Montant TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $i => $t)
            <tr class="{{ $t->status === 'cancelled' ? 'cancelled' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td>{{ $t->reference }}</td>
                <td>{{ \Carbon\Carbon::parse($t->issued_at)->format('H:i') }}</td>
                <td>{{ $t->thirdPartner?->tp_title ?? 'Client Comptoir' }}</td>
                <td>
                    @if($t->status === 'cancelled')
                        <span style="color:#dc2626; font-weight:bold;">Annulé</span>
                    @elseif($t->status === 'partial')
                        <span style="color:#f59e0b; font-weight:bold;">Partiel</span>
                    @elseif($t->status === 'pending')
                        <span style="color:#f59e0b; font-weight:bold;">En Compte</span>
                    @else
                        <span style="color:#16a34a;">Payé</span>
                    @endif
                </td>
                <td>
                    @php
                        $methods = $t->payments->pluck('method')->unique()->map(fn($m) => $methodLabels[$m] ?? ucfirst($m))->implode(', ');
                    @endphp
                    {{ $methods ?: '—' }}
                </td>
                <td class="right">{{ number_format($t->footer?->total_ttc ?? 0, 2, ',', ' ') }} MAD</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Notes ───────────────────────────────────── --}}
    @if($session->notes)
    <div style="margin-top: 15px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <strong style="font-size: 9px; color: #1e3a5f;">Notes :</strong>
        <p style="font-size: 9px; color: #555; margin-top: 3px;">{{ $session->notes }}</p>
    </div>
    @endif

    {{-- ── Footer ──────────────────────────────────── --}}
    <div class="page-footer">
        Rapport généré le {{ now()->format('d/m/Y à H:i') }} — {{ $company['name'] }}
        @if($company['phone']) — Tél : {{ $company['phone'] }}@endif
    </div>

</div>
</body>
</html>
