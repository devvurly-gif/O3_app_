<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de paiement</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .wrapper { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 28px 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .header p { color: #bfdbfe; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #374151; margin-bottom: 20px; }
        .payment-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 20px; margin-bottom: 24px; }
        .payment-box h3 { margin: 0 0 12px; font-size: 14px; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px; }
        .payment-grid { display: table; width: 100%; }
        .payment-row { display: table-row; }
        .payment-label { display: table-cell; padding: 4px 0; font-size: 13px; color: #6b7280; width: 45%; }
        .payment-value { display: table-cell; padding: 4px 0; font-size: 13px; font-weight: 600; color: #111827; }
        .amount-big { font-size: 22px; color: #059669; font-weight: 700; }
        table.invoices { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
        table.invoices th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.3px; border-bottom: 2px solid #e5e7eb; }
        table.invoices td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
        table.invoices tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .mono { font-family: 'Courier New', monospace; }
        .badge-paid { display: inline-block; background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-partial { display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .situation-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 20px; margin-bottom: 24px; }
        .situation-box h3 { margin: 0 0 14px; font-size: 14px; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; }
        .situation-grid { display: table; width: 100%; }
        .situation-row { display: table-row; }
        .situation-label { display: table-cell; padding: 5px 0; font-size: 13px; color: #6b7280; }
        .situation-value { display: table-cell; padding: 5px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right; }
        .text-red { color: #dc2626; }
        .text-green { color: #059669; }
        .footer { background: #f9fafb; padding: 20px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 4px 0; font-size: 12px; color: #9ca3af; }
        .footer .company-name { font-weight: 600; color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <!-- Header -->
            <div class="header">
                <h1>Confirmation de paiement</h1>
                <p>{{ $company['name'] }}</p>
            </div>

            <!-- Body -->
            <div class="body">
                <p class="greeting">Bonjour <strong>{{ $partner->tp_title }}</strong>,</p>
                <p style="font-size: 14px; color: #4b5563; margin-bottom: 24px;">
                    Nous confirmons la réception de votre paiement. Veuillez trouver ci-dessous le récapitulatif.
                </p>

                <!-- Payment details -->
                <div class="payment-box">
                    <h3>Détails du paiement</h3>
                    <div class="payment-grid">
                        <div class="payment-row">
                            <span class="payment-label">Montant</span>
                            <span class="payment-value amount-big">{{ number_format($totalPaid, 2, ',', ' ') }} DH</span>
                        </div>
                        <div class="payment-row">
                            <span class="payment-label">Méthode</span>
                            <span class="payment-value">{{ $methodLabel }}</span>
                        </div>
                        @if($reference)
                        <div class="payment-row">
                            <span class="payment-label">Référence</span>
                            <span class="payment-value mono">{{ $reference }}</span>
                        </div>
                        @endif
                        <div class="payment-row">
                            <span class="payment-label">Date</span>
                            <span class="payment-value">{{ now()->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Affected invoices -->
                @if(count($affectedInvoices) > 0)
                <h3 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Factures concernées</h3>
                <table class="invoices">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th class="text-right">Montant affecté</th>
                            <th class="text-right">Reste dû</th>
                            <th class="text-right">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($affectedInvoices as $inv)
                        <tr>
                            <td class="mono">{{ $inv['reference'] }}</td>
                            <td class="text-right mono">{{ number_format($inv['amount_applied'], 2, ',', ' ') }} DH</td>
                            <td class="text-right mono {{ $inv['amount_due'] > 0 ? 'text-red' : 'text-green' }}">
                                {{ number_format($inv['amount_due'], 2, ',', ' ') }} DH
                            </td>
                            <td class="text-right">
                                @if($inv['is_paid'])
                                    <span class="badge-paid">Soldée</span>
                                @else
                                    <span class="badge-partial">Partiel</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <!-- Financial situation -->
                <div class="situation-box">
                    <h3>Votre situation</h3>
                    <div class="situation-grid">
                        <div class="situation-row">
                            <span class="situation-label">Total restant dû</span>
                            <span class="situation-value {{ $totalDueRemaining > 0 ? 'text-red' : 'text-green' }}">
                                {{ number_format($totalDueRemaining, 2, ',', ' ') }} DH
                            </span>
                        </div>
                        <div class="situation-row">
                            <span class="situation-label">Encours crédit</span>
                            <span class="situation-value">{{ number_format($encoursActuel, 2, ',', ' ') }} DH</span>
                        </div>
                        @if($seuilCredit > 0)
                        <div class="situation-row">
                            <span class="situation-label">Seuil crédit</span>
                            <span class="situation-value">{{ number_format($seuilCredit, 2, ',', ' ') }} DH</span>
                        </div>
                        <div class="situation-row">
                            <span class="situation-label">Crédit disponible</span>
                            <span class="situation-value text-green">{{ number_format($seuilCredit - $encoursActuel, 2, ',', ' ') }} DH</span>
                        </div>
                        @endif
                    </div>
                </div>

                <p style="font-size: 13px; color: #6b7280;">Merci pour votre confiance.</p>
            </div>

            <!-- Footer -->
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
                @if($company['ice'])
                <p>ICE : {{ $company['ice'] }}</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
