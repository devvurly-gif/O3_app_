<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Ventes</title>
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
        .doc-title { font-size: 20px; font-weight: bold; color: #1e3a5f; margin-bottom: 4px; }
        .doc-subtitle { font-size: 10px; color: #666; }

        .separator { border-top: 2px solid #1e3a5f; margin: 15px 0; }

        .cards { display: table; width: 100%; margin-bottom: 20px; }
        .card { display: table-cell; width: 25%; padding: 8px; vertical-align: top; }
        .card-inner { border: 1px solid #ddd; border-radius: 4px; padding: 10px 12px; }
        .card-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 3px; }
        .card-value { font-size: 14px; font-weight: bold; color: #1e3a5f; }
        .card-sub { font-size: 8px; color: #888; margin-top: 2px; }

        .section-title { font-size: 12px; font-weight: bold; color: #1e3a5f; margin: 18px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data th {
            background: #1e3a5f; color: white; font-size: 8px; text-transform: uppercase;
            letter-spacing: 0.3px; padding: 7px 10px; text-align: left;
        }
        table.data th.right { text-align: right; }
        table.data td { padding: 6px 10px; font-size: 9px; border-bottom: 1px solid #eee; }
        table.data td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.data tr:nth-child(even) { background: #f9fafb; }

        .footer { margin-top: 30px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
<div class="page">
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-info">
                @if($company['address']){{ $company['address'] }}<br>@endif
                @if($company['city']){{ $company['city'] }}<br>@endif
                @if($company['phone'])Tél : {{ $company['phone'] }}<br>@endif
                @if($company['email']){{ $company['email'] }}<br>@endif
                @if($company['ice'])ICE : {{ $company['ice'] }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">Rapport des Ventes</div>
            <div class="doc-subtitle">
                Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}<br>
                Généré le {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- KPI Cards --}}
    <div class="cards">
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Chiffre d'Affaires TTC</div>
                <div class="card-value">{{ number_format($data['totals']['revenue_ttc'], 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Chiffre d'Affaires HT</div>
                <div class="card-value">{{ number_format($data['totals']['revenue_ht'], 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Total TVA</div>
                <div class="card-value">{{ number_format($data['totals']['total_tax'], 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Nombre de Factures</div>
                <div class="card-value">{{ $data['totals']['invoice_count'] }}</div>
            </div>
        </div>
    </div>

    {{-- Documents par type --}}
    @if(count($data['by_type']) > 0)
    <div class="section-title">Documents par Type</div>
    <table class="data">
        <thead>
            <tr>
                <th>Type</th>
                <th class="right">Nombre</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['by_type'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="right">{{ $row['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Documents par statut --}}
    @if(count($data['by_status']) > 0)
    <div class="section-title">Documents par Statut</div>
    <table class="data">
        <thead>
            <tr>
                <th>Statut</th>
                <th class="right">Nombre</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['by_status'] as $row)
            <tr>
                <td>{{ ucfirst($row['status']) }}</td>
                <td class="right">{{ $row['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top 10 Produits --}}
    @if(count($data['top_products']) > 0)
    <div class="section-title">Top 10 Produits par CA</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 50%">Produit</th>
                <th class="right">Qté vendue</th>
                <th class="right">CA TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['top_products'] as $row)
            <tr>
                <td>{{ $row['designation'] }}</td>
                <td class="right">{{ number_format($row['total_qty'], 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($row['total_revenue'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top 10 Clients --}}
    @if(count($data['top_clients']) > 0)
    <div class="section-title">Top 10 Clients par CA</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 50%">Client</th>
                <th class="right">Factures</th>
                <th class="right">CA TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['top_clients'] as $row)
            <tr>
                <td>{{ $row['tp_title'] }}</td>
                <td class="right">{{ $row['invoice_count'] }}</td>
                <td class="right">{{ number_format($row['total_revenue'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Paiements par méthode --}}
    @if(count($data['payments_by_method']) > 0)
    <div class="section-title">Paiements par Méthode</div>
    <table class="data">
        <thead>
            <tr>
                <th>Méthode</th>
                <th class="right">Nombre</th>
                <th class="right">Montant Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['payments_by_method'] as $row)
            <tr>
                <td>{{ ucfirst($row['method']) }}</td>
                <td class="right">{{ $row['count'] }}</td>
                <td class="right">{{ number_format($row['total'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Revenue journalier --}}
    @if(count($data['daily_revenue']) > 0)
    <div class="section-title">Revenus Journaliers</div>
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th class="right">CA TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['daily_revenue'] as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['day'])->format('d/m/Y') }}</td>
                <td class="right">{{ number_format($row['total'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        {{ $company['name'] }} — Rapport généré automatiquement le {{ now()->format('d/m/Y à H:i') }}
    </div>
</div>
</body>
</html>
