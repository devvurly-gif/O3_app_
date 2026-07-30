<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de commande</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .wrapper { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 28px 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .header p { color: #bfdbfe; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #374151; margin-bottom: 8px; }
        .ref-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 20px; margin-bottom: 24px; }
        .ref-box .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #1e40af; }
        .ref-box .value { font-size: 18px; font-weight: 700; color: #111827; font-family: 'Courier New', monospace; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
        table.items th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.3px; border-bottom: 2px solid #e5e7eb; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
        table.items tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .totals-box { background: #f9fafb; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; }
        .totals-grid { display: table; width: 100%; }
        .totals-row { display: table-row; }
        .totals-label { display: table-cell; padding: 4px 0; font-size: 13px; color: #6b7280; }
        .totals-value { display: table-cell; padding: 4px 0; font-size: 13px; font-weight: 600; color: #111827; text-align: right; }
        .totals-row.grand .totals-label, .totals-row.grand .totals-value { font-size: 16px; color: #059669; padding-top: 8px; }
        .ship-box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; }
        .ship-box h3 { margin: 0 0 8px; font-size: 13px; color: #374151; }
        .ship-box p { margin: 2px 0; font-size: 13px; color: #4b5563; }
        .footer { background: #f9fafb; padding: 20px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 4px 0; font-size: 12px; color: #9ca3af; }
        .footer .company-name { font-weight: 600; color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>Commande confirmée</h1>
                <p>{{ $company['name'] }}</p>
            </div>

            <div class="body">
                <p class="greeting">Bonjour <strong>{{ $document->ship_name }}</strong>,</p>
                <p style="font-size: 14px; color: #4b5563; margin-bottom: 20px;">
                    Nous avons bien reçu votre commande. Voici le récapitulatif.
                </p>

                <div class="ref-box">
                    <div class="label">Référence de commande</div>
                    <div class="value">{{ $document->reference }}</div>
                </div>

                <table class="items">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th class="text-right">Qté</th>
                            <th class="text-right">Prix unitaire</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($document->lignes as $ligne)
                        <tr>
                            <td>{{ $ligne->designation }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format($ligne->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="text-right">{{ number_format($ligne->unit_price, 2, ',', ' ') }} DH</td>
                            <td class="text-right">{{ number_format($ligne->total_ligne_ht, 2, ',', ' ') }} DH</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="totals-box">
                    <div class="totals-grid">
                        <div class="totals-row">
                            <span class="totals-label">Total HT</span>
                            <span class="totals-value">{{ number_format($document->footer->total_ht ?? 0, 2, ',', ' ') }} DH</span>
                        </div>
                        <div class="totals-row">
                            <span class="totals-label">TVA</span>
                            <span class="totals-value">{{ number_format($document->footer->total_tax ?? 0, 2, ',', ' ') }} DH</span>
                        </div>
                        <div class="totals-row grand">
                            <span class="totals-label">Total TTC</span>
                            <span class="totals-value">{{ number_format($document->footer->total_ttc ?? 0, 2, ',', ' ') }} DH</span>
                        </div>
                    </div>
                </div>

                <div class="ship-box">
                    <h3>Adresse de livraison</h3>
                    <p>{{ $document->ship_name }}</p>
                    <p>{{ $document->ship_address }}, {{ $document->ship_city }}</p>
                    <p>{{ $document->ship_phone }}</p>
                </div>

                <p style="font-size: 13px; color: #6b7280;">Nous vous tiendrons informé(e) de la préparation de votre commande. Merci pour votre confiance.</p>
            </div>

            <div class="footer">
                <p class="company-name">{{ $company['name'] }}</p>
                @if($company['address'] || $company['city'])
                <p>{{ $company['address'] }} {{ $company['city'] }}</p>
                @endif
                @if($company['phone'])
                <p>Tél : {{ $company['phone'] }}</p>
                @endif
                @if($company['email'])
                <p>{{ $company['email'] }}</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
