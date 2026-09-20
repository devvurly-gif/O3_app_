<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; margin: 0; padding: 0; background: #f4f4f5; }
        .container { max-width: 600px; margin: 24px auto; }
        .header { background: #1F4E79; color: white; padding: 28px 24px; border-radius: 8px 8px 0 0; }
        .header.warn { background: #b45309; }
        .header h1 { margin: 0; font-size: 21px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { background: #ffffff; padding: 28px 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; padding: 14px 32px; background: #1F4E79; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .info { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 14px 18px; margin: 20px 0; font-size: 13px; }
        .alt-link { font-size: 12px; color: #6b7280; word-break: break-all; background: #f9fafb; padding: 10px 14px; border-radius: 6px; margin-top: 16px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 16px; padding: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $daysLeft <= 0 ? 'warn' : '' }}">
            @if ($daysLeft <= 0)
                <h1>{{ $isTrial ? "Votre essai est terminé" : "Votre abonnement est arrivé à échéance" }}</h1>
                <p>{{ $companyName }} — formule {{ $planName }}</p>
            @else
                <h1>Plus que {{ $daysLeft }} {{ $daysLeft === 1 ? 'jour' : 'jours' }}</h1>
                <p>{{ $companyName }} — formule {{ $planName }}</p>
            @endif
        </div>

        <div class="body">
            @if ($daysLeft <= 0)
                <p>
                    {{ $isTrial
                        ? "Votre période d'essai s'est achevée le {$endsAt}."
                        : "Votre abonnement est arrivé à échéance le {$endsAt}." }}
                </p>

                <p>
                    <strong>Vos données sont intactes et restent consultables.</strong>
                    La saisie de nouveaux documents est simplement suspendue jusqu'au règlement.
                </p>
            @else
                <p>
                    {{ $isTrial
                        ? "Votre période d'essai se termine le {$endsAt}."
                        : "Votre abonnement arrive à échéance le {$endsAt}." }}
                </p>

                <p>Pour continuer sans interruption, choisissez votre formule depuis votre espace :</p>
            @endif

            <div class="cta-wrap">
                <a href="{{ $subscriptionUrl }}" class="cta">
                    {{ $daysLeft <= 0 ? 'Réactiver mon espace' : 'Choisir ma formule' }}
                </a>
            </div>

            <div class="alt-link">{{ $subscriptionUrl }}</div>

            <div class="info">
                Une question sur les formules, ou besoin d'un devis&nbsp;? Répondez simplement
                à cet email, nous vous rappelons.
            </div>
        </div>

        <div class="footer">O3 App</div>
    </div>
</body>
</html>
