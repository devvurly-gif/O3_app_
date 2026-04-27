<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .container { max-width: 620px; margin: 0 auto; padding: 0; }
        .header { background: #1F4E79; color: white; padding: 24px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; font-size: 13px; opacity: 0.9; }
        .body { background: #ffffff; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .custom-msg { background: #f9fafb; border-left: 3px solid #1F4E79; padding: 12px 16px; margin: 16px 0; font-style: italic; color: #4b5563; }
        .attachment-list { background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; margin: 16px 0; }
        .attachment-list strong { color: #78350f; }
        .attachment-list ul { margin: 8px 0 0 0; padding-left: 20px; }
        .attachment-list li { margin: 4px 0; font-size: 13px; color: #4b5563; }
        .steps { margin: 20px 0; padding-left: 20px; }
        .steps li { margin: 8px 0; }
        .signature { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 16px; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Contrat de services {{ $companyName }}</h1>
            <p>Documents joints pour signature électronique</p>
        </div>

        <div class="body">
            <p class="greeting">Bonjour {{ $clientName }},</p>

            <p>
                Vous trouverez ci-joint le contrat de services SaaS pour la mise à disposition de la solution
                <strong>{{ $companyName }}</strong>, ainsi que la fiche de souscription à compléter.
            </p>

            @if ($customMessage)
                <div class="custom-msg">{!! nl2br(e($customMessage)) !!}</div>
            @endif

            <div class="attachment-list">
                <strong>📎 Pièces jointes</strong>
                <ul>
                    <li><strong>contrat-services-saas.docx</strong> — Conditions Générales de Service</li>
                    @if ($includeIntakeForm)
                        <li><strong>fiche-souscription-client.docx</strong> — Fiche de souscription à compléter</li>
                    @endif
                </ul>
            </div>

            <p><strong>Procédure suggérée :</strong></p>
            <ol class="steps">
                @if ($includeIntakeForm)
                    <li>Compléter la <strong>fiche de souscription</strong> avec les informations de votre entreprise et le périmètre souhaité.</li>
                    <li>Nous retourner la fiche complétée par email.</li>
                    <li>Nous établirons sur cette base le contrat définitif, qui vous sera renvoyé pour <strong>signature électronique</strong>.</li>
                @else
                    <li>Lire attentivement le contrat de services.</li>
                    <li>Le retourner signé par <strong>signature électronique</strong> via le lien qui vous sera transmis.</li>
                @endif
                <li>À réception du contrat signé et du paiement, votre espace sera mis en service sous 5 jours ouvrés.</li>
            </ol>

            <p>
                Conformément à la loi marocaine n° 53-05 relative à l'échange électronique de données juridiques,
                la signature électronique a la même valeur juridique qu'une signature manuscrite.
            </p>

            <p>Pour toute question, n'hésitez pas à nous contacter en répondant à cet email.</p>

            <div class="signature">
                Cordialement,<br>
                <strong>{{ $companyName }}</strong>
            </div>
        </div>

        <div class="footer">
            Cet email a été envoyé par {{ $companyName }} dans le cadre de votre demande de souscription.<br>
            Les pièces jointes contiennent vos coordonnées professionnelles.
        </div>
    </div>
</body>
</html>
