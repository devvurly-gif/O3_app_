<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f5; }
        .container { max-width: 600px; margin: 24px auto; }
        .header { background: #1F4E79; color: white; padding: 28px 24px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { background: #ffffff; padding: 28px 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; padding: 14px 32px; background: #1F4E79; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .alt-link { font-size: 12px; color: #6b7280; word-break: break-all; background: #f9fafb; padding: 10px 14px; border-radius: 6px; margin-top: 16px; }
        .info { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 14px 18px; margin: 20px 0; font-size: 13px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 16px; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenue sur O3 App, {{ $adminName }} 👋</h1>
            <p>Une dernière étape pour activer {{ $companyName }}</p>
        </div>

        <div class="body">
            <p>Merci pour votre inscription. Votre espace <strong>{{ $domain }}</strong> est prêt à être activé.</p>

            <p>Cliquez sur le bouton ci-dessous pour confirmer votre adresse email et démarrer votre période d'essai gratuite de 14 jours :</p>

            <div class="cta-wrap">
                <a href="{{ $verifyUrl }}" class="cta">Activer mon espace</a>
            </div>

            <p style="font-size: 13px; color: #6b7280;">Le lien est valide 24 heures. Si vous n'arrivez pas à cliquer dessus, copiez-collez l'URL suivante dans votre navigateur :</p>
            <div class="alt-link">{{ $verifyUrl }}</div>

            <div class="info">
                <strong>Que se passe-t-il après l'activation ?</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                    <li>Vous accédez immédiatement à votre tableau de bord sur <strong>{{ $domain }}</strong></li>
                    <li>14 jours d'essai gratuit, sans carte bancaire</li>
                    <li>À la fin de l'essai, nous vous contacterons pour finaliser l'abonnement</li>
                </ul>
            </div>

            <p style="font-size: 13px; color: #6b7280;">Vous n'avez pas demandé cette inscription ? Ignorez simplement cet email — votre espace sera supprimé automatiquement dans 24 heures s'il n'est pas activé.</p>
        </div>

        <div class="footer">
            O3 App — ERP Cloud 100% Marocain<br>
            Cet email a été envoyé à la suite d'une inscription sur o3app.ma
        </div>
    </div>
</body>
</html>
