<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f5; }
        .container { max-width: 600px; margin: 24px auto; }
        .header { background: #1F4E79; color: white; padding: 28px 24px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { background: #ffffff; padding: 28px 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        table.rows { width: 100%; border-collapse: collapse; margin: 8px 0 20px; }
        table.rows td { padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        table.rows td.k { color: #6b7280; width: 190px; font-size: 13px; }
        table.rows td.v { font-weight: 600; }
        .total { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 14px 18px; margin-bottom: 20px; font-size: 15px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 16px; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Facture {{ $invoice->number }}</h1>
            <p>Abonnement O3 App — {{ $invoice->period_starts_at?->format('d/m/Y') }} au {{ $invoice->period_ends_at?->format('d/m/Y') }}</p>
        </div>

        <div class="body">
            <p>Bonjour,</p>

            <p>Vous trouverez en pièce jointe la facture de votre abonnement pour la période à venir.</p>

            <table class="rows">
                <tr><td class="k">Numéro</td><td class="v">{{ $invoice->number }}</td></tr>
                <tr><td class="k">Date</td><td class="v">{{ $invoice->issued_at?->format('d/m/Y') }}</td></tr>
                <tr><td class="k">Période</td><td class="v">{{ $invoice->period_starts_at?->format('d/m/Y') }} — {{ $invoice->period_ends_at?->format('d/m/Y') }}</td></tr>
                <tr><td class="k">À régler avant le</td><td class="v">{{ $invoice->due_at?->format('d/m/Y') }}</td></tr>
            </table>

            <div class="total">
                <strong>Total à régler : {{ number_format($invoice->amount_ttc_cents / 100, 2, ',', ' ') }} MAD TTC</strong>
            </div>

            @if (!empty($issuer['iban']))
                <p style="font-size: 13px;">
                    Règlement par virement : {{ $issuer['bank'] ?? '' }} — IBAN {{ $issuer['iban'] }}.<br>
                    Merci de rappeler le numéro <strong>{{ $invoice->number }}</strong> sur votre ordre.
                </p>
            @endif

            <p style="font-size: 13px; color: #6b7280;">
                Une question sur cette facture ? Répondez simplement à cet email.
            </p>
        </div>

        <div class="footer">{{ $issuer['name'] ?? 'O3 App' }}</div>
    </div>
</body>
</html>
