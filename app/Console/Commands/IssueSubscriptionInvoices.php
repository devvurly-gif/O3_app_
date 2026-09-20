<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BillingNotConfiguredException;
use App\Models\Tenant;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Emet et envoie les factures d'abonnement dont l'echeance approche.
 *
 * Tourne apres `subscriptions:check` : les statuts sont a jour, et un tenant
 * qui vient de basculer en impaye n'a pas besoin d'une facture de plus — il a
 * deja la sienne, emise quinze jours plus tot.
 *
 * Le contrat prevoit une facturation d'avance (article 8.3) : la facture part
 * `billing.issue_lead_days` avant l'echeance, pour la periode suivante. Un
 * client ne peut pas regler une echeance dont il n'a pas encore la facture.
 */
class IssueSubscriptionInvoices extends Command
{
    protected $signature = 'subscriptions:invoice
                            {--tenant= : Ne traiter qu\'un tenant}
                            {--dry-run : Afficher ce qui serait facturé, sans rien émettre ni envoyer}
                            {--no-send : Émettre les factures sans les envoyer}';

    protected $description = "Émet et envoie les factures d'abonnement des échéances proches";

    public function handle(SubscriptionInvoiceService $invoices): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // L'identite de facturation est verifiee une fois, avant la boucle :
        // inutile de lever la meme exception pour chaque tenant, et surtout
        // inutile d'emettre la premiere facture avant de decouvrir le probleme
        // sur la deuxieme.
        if ($missing = $invoices->missingIssuerFields()) {
            $this->error("Aucune facture ne peut être émise — identité de facturation incomplète.");
            $this->line('  Champs manquants : ' . implode(', ', $missing));
            $this->line('  À renseigner dans le .env du serveur (voir config/billing.php),');
            $this->line("  puis relancer 'php artisan config:cache'.");

            return self::FAILURE;
        }

        $tenants = $invoices->tenantsDueForInvoice();

        if ($tenantId = $this->option('tenant')) {
            $tenants = $tenants->where('id', $tenantId)->values();

            // Emission manuelle : on force, meme si l'echeance est lointaine.
            if ($tenants->isEmpty() && ($tenant = Tenant::find($tenantId))) {
                $tenants = collect([$tenant]);
            }
        }

        if ($tenants->isEmpty()) {
            $this->info('Aucune facture à émettre aujourd\'hui.');

            return self::SUCCESS;
        }

        $issued = 0;
        $sent   = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            if ($dryRun) {
                $this->line(sprintf('  %-20s à facturer (%s)', $tenant->id, $tenant->plan));
                $issued++;
                continue;
            }

            try {
                $invoice = $invoices->issueFor($tenant);
                $issued++;
                $this->line(sprintf(
                    '  %-20s %s — %s TTC, échéance %s',
                    $tenant->id,
                    $invoice->number,
                    number_format($invoice->amount_ttc_cents / 100, 2, ',', ' '),
                    $invoice->due_at->format('d/m/Y')
                ));
            } catch (BillingNotConfiguredException $e) {
                // Deja filtre plus haut ; si on arrive ici, la config a change
                // en cours de route — on arrete, plutot que de produire des
                // factures a moitie conformes.
                $this->error("  {$e->getMessage()}");

                return self::FAILURE;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Émission de facture d\'abonnement échouée', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("  {$tenant->id} : {$e->getMessage()}");
                continue;
            }

            if ($this->option('no-send')) {
                continue;
            }

            try {
                $invoices->send($invoice);
                $sent++;
            } catch (\Throwable $e) {
                // La facture existe et reste renvoyable depuis le back-office :
                // un email qui ne part pas ne doit pas faire disparaitre un
                // document numerote.
                Log::error('Envoi de facture d\'abonnement échoué', [
                    'invoice' => $invoice->number,
                    'error'   => $e->getMessage(),
                ]);
                $this->warn("    envoi échoué ({$e->getMessage()}) — facture conservée, à renvoyer depuis le back-office");
            }
        }

        $this->info(sprintf(
            '%d facture(s) %s, %d envoyée(s), %d échec(s).',
            $issued,
            $dryRun ? 'à émettre (simulation)' : 'émise(s)',
            $sent,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
