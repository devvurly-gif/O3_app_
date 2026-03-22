<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1e3a5f; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .body { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e5e7eb; }
        .info-label { color: #666; }
        .info-value { font-weight: bold; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .summary-table th { background: #1e3a5f; color: white; padding: 8px 12px; text-align: left; font-size: 12px; }
        .summary-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .summary-table td.right { text-align: right; font-weight: 600; }
        .diff-positive { color: #16a34a; font-weight: bold; }
        .diff-negative { color: #dc2626; font-weight: bold; }
        .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Rapport de Fermeture de Session</h1>
            <p style="margin:5px 0 0; font-size:13px; opacity:0.8;">{{ $company['name'] }}</p>
        </div>

        <div class="body">
            <p>Bonjour,</p>
            <p>La session POS suivante a été fermée :</p>

            <table class="summary-table">
                <tr>
                    <td class="info-label">Terminal</td>
                    <td class="right">{{ $session->terminal->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Caissier</td>
                    <td class="right">{{ $session->user->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Ouverture</td>
                    <td class="right">{{ $session->opened_at instanceof \Carbon\Carbon ? $session->opened_at->format('d/m/Y H:i') : \Carbon\Carbon::parse($session->opened_at)->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td class="info-label">Fermeture</td>
                    <td class="right">{{ $session->closed_at instanceof \Carbon\Carbon ? $session->closed_at->format('d/m/Y H:i') : \Carbon\Carbon::parse($session->closed_at)->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <h3 style="color:#1e3a5f; margin-top:20px;">Résumé</h3>
            <table class="summary-table">
                <tr>
                    <td>Nombre de tickets</td>
                    <td class="right">{{ $stats['total_tickets'] }}</td>
                </tr>
                <tr>
                    <td>CA TTC</td>
                    <td class="right">{{ number_format($stats['total_ttc'], 2, ',', ' ') }} MAD</td>
                </tr>
                <tr>
                    <td>Tickets annulés</td>
                    <td class="right" style="color:#dc2626;">{{ $stats['cancelled_tickets'] }}</td>
                </tr>
            </table>

            <h3 style="color:#1e3a5f; margin-top:20px;">Paiements par Mode</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Mode</th>
                        <th style="text-align:right;">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $methodLabels = [
                            'cash' => 'Espèces',
                            'card' => 'Carte bancaire',
                            'credit' => 'En Compte',
                            'cheque' => 'Chèque',
                            'bank_transfer' => 'Virement',
                            'effet' => 'Effet',
                        ];
                    @endphp
                    @foreach($stats['payments_by_method'] as $method => $amount)
                    <tr>
                        <td>{{ $methodLabels[$method] ?? ucfirst($method) }}</td>
                        <td class="right">{{ number_format($amount, 2, ',', ' ') }} MAD</td>
                    </tr>
                    @endforeach
                    <tr style="border-top:2px solid #1e3a5f;">
                        <td style="font-weight:bold;">Total</td>
                        <td class="right" style="font-weight:bold;">{{ number_format($stats['total_paid'], 2, ',', ' ') }} MAD</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="color:#1e3a5f; margin-top:20px;">Réconciliation Caisse</h3>
            <table class="summary-table">
                <tr>
                    <td>Espèces attendues</td>
                    <td class="right">{{ number_format($session->expected_cash, 2, ',', ' ') }} MAD</td>
                </tr>
                <tr>
                    <td>Espèces comptées</td>
                    <td class="right">{{ number_format($session->closing_cash, 2, ',', ' ') }} MAD</td>
                </tr>
                <tr style="border-top:2px solid #1e3a5f;">
                    <td style="font-weight:bold;">Différence</td>
                    <td class="right {{ $session->cash_difference >= 0 ? 'diff-positive' : 'diff-negative' }}">
                        {{ $session->cash_difference >= 0 ? '+' : '' }}{{ number_format($session->cash_difference, 2, ',', ' ') }} MAD
                    </td>
                </tr>
            </table>

            <p style="margin-top:20px; font-size:13px; color:#666;">
                Le rapport PDF détaillé avec la liste complète des tickets est joint à cet email.
            </p>
        </div>

        <div class="footer">
            <p>{{ $company['name'] }} — Système POS</p>
        </div>
    </div>
</body>
</html>
