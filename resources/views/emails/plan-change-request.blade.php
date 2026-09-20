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
        table.rows td { padding: 9px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.rows td.k { color: #6b7280; width: 190px; font-size: 13px; }
        table.rows td.v { font-weight: 600; }
        .note { background: #f9fafb; border-left: 3px solid #cbd5e1; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; }
        .todo { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 14px 18px; font-size: 13px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 16px; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }} demande la formule {{ $planName }}</h1>
            <p>Demande enregistrée depuis l'espace client</p>
        </div>

        <div class="body">
            <table class="rows">
                <tr><td class="k">Tenant</td><td class="v">{{ $tenantId }}</td></tr>
                <tr><td class="k">Société</td><td class="v">{{ $companyName }}</td></tr>
                <tr><td class="k">Formule demandée</td><td class="v">{{ $planName }} ({{ $plan }})</td></tr>
                <tr><td class="k">Périodicité</td><td class="v">{{ $billingPeriod === 'yearly' ? 'Annuelle' : 'Mensuelle' }}</td></tr>
                <tr><td class="k">Email</td><td class="v">{{ $email }}</td></tr>
                <tr><td class="k">Téléphone</td><td class="v">{{ $phone ?: '—' }}</td></tr>
            </table>

            @if ($note)
                <div class="note"><strong>Message du client :</strong><br>{{ $note }}</div>
            @endif

            <div class="todo">
                <strong>À faire</strong>
                <ol style="margin: 8px 0 0 0; padding-left: 20px;">
                    <li>Envoyer le contrat et la fiche de souscription depuis le back-office central</li>
                    <li>Émettre la facture d'abonnement</li>
                    <li>À réception du règlement, l'enregistrer sur la fiche du tenant — l'échéance se repousse automatiquement</li>
                </ol>
            </div>
        </div>

        <div class="footer">O3 App — notification automatique</div>
    </div>
</body>
</html>
