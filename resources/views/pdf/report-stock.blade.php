<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Stock</title>
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
        .card { display: table-cell; width: 33.33%; padding: 8px; vertical-align: top; }
        .card-inner { border: 1px solid #ddd; border-radius: 4px; padding: 10px 12px; }
        .card-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 3px; }
        .card-value { font-size: 14px; font-weight: bold; color: #1e3a5f; }

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

        .text-red { color: #dc2626; font-weight: bold; }
        .text-orange { color: #ea580c; font-weight: bold; }

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
            <div class="doc-title">Rapport de Stock</div>
            <div class="doc-subtitle">
                {{ $warehouse }}<br>
                Généré le {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- KPI Cards --}}
    <div class="cards">
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Produits en Stock</div>
                <div class="card-value">{{ $data['total_value']['product_count'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Quantité Totale</div>
                <div class="card-value">{{ number_format($data['total_value']['total_qty'], 2, ',', ' ') }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="card-label">Valeur du Stock</div>
                <div class="card-value">{{ number_format($data['total_value']['total_value'], 2, ',', ' ') }}</div>
            </div>
        </div>
    </div>

    {{-- Mouvements --}}
    @if(count($data['movements_summary']) > 0)
    <div class="section-title">Résumé des Mouvements</div>
    <table class="data">
        <thead>
            <tr>
                <th>Direction</th>
                <th class="right">Nombre</th>
                <th class="right">Quantité Totale</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['movements_summary'] as $row)
            <tr>
                <td>{{ $row['direction'] === 'in' ? 'Entrées' : 'Sorties' }}</td>
                <td class="right">{{ $row['count'] }}</td>
                <td class="right">{{ number_format($row['total_qty'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Ruptures de stock --}}
    @if(count($data['out_of_stock']) > 0)
    <div class="section-title">Ruptures de Stock ({{ count($data['out_of_stock']) }} produits)</div>
    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Entrepôt</th>
                <th class="right">Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['out_of_stock'] as $row)
            <tr>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td>{{ $row['warehouse'] }}</td>
                <td class="right text-red">{{ number_format($row['stockLevel'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Stock faible --}}
    @if(count($data['low_stock']) > 0)
    <div class="section-title">Stock Faible ({{ count($data['low_stock']) }} produits)</div>
    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Entrepôt</th>
                <th class="right">Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['low_stock'] as $row)
            <tr>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td>{{ $row['warehouse'] }}</td>
                <td class="right text-orange">{{ number_format($row['stockLevel'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Stock complet --}}
    @if(count($data['current_stock']) > 0)
    <div class="section-title">État du Stock Complet ({{ count($data['current_stock']) }} lignes)</div>
    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Entrepôt</th>
                <th class="right">Stock</th>
                <th class="right">Coût Unit.</th>
                <th class="right">Valeur</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['current_stock'] as $row)
            <tr>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td>{{ $row['warehouse'] }}</td>
                <td class="right">{{ number_format($row['stockLevel'], 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($row['cost_price'], 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($row['value'], 2, ',', ' ') }}</td>
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
