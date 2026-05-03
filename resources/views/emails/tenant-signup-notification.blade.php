<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f5; }
        .container { max-width: 600px; margin: 24px auto; }
        .header { background: #16a34a; color: white; padding: 24px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 700; }
        .header p { margin: 4px 0 0; opacity: 0.9; font-size: 12px; }
        .body { background: #ffffff; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        table.info { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.info td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        table.info td:first-child { color: #6b7280; width: 35%; }
        table.info td:last-child { font-weight: 600; color: #111827; }
        .cta-wrap { margin: 20px 0; }
        .cta { display: inline-block; padding: 10px 20px; background: #1F4E79; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px; margin-right: 8px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 12px; padding: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Nouveau tenant: {{ $companyName }}</h1>
            <p>{{ $tenantId }} · vient de s'inscrire via o3app.ma</p>
        </div>

        <div class="body">
            <p>Une nouvelle inscription publique vient d'arriver. Le tenant a été provisionné en mode <strong>inactif</strong> et attend la confirmation par email.</p>

            <table class="info">
                <tr><td>Sous-domaine</td><td>{{ $domain }}</td></tr>
                <tr><td>Raison sociale</td><td>{{ $companyName }}</td></tr>
                <tr><td>Contact</td><td>{{ $adminName }}</td></tr>
                <tr><td>Email</td><td>{{ $email }}</td></tr>
                @if ($phone)
                    <tr><td>Téléphone</td><td>{{ $phone }}</td></tr>
                @endif
                <tr><td>Plan</td><td>Starter (essai 14j)</td></tr>
            </table>

            <div class="cta-wrap">
                <a href="https://o3app.ma/central/tenants/{{ $tenantId }}" class="cta">Voir dans l'admin</a>
            </div>

            <p style="font-size: 12px; color: #6b7280; margin-top: 16px;">
                Le client doit cliquer sur le lien de vérification dans son email pour activer le tenant. Si vous voulez l'activer manuellement, utilisez le bouton "Activer" dans la fiche admin.
            </p>
        </div>

        <div class="footer">
            O3 App — Notification d'inscription publique
        </div>
    </div>
</body>
</html>
