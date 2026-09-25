<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('achats:draft-daily-po')->dailyAt('07:30');
        $schedule->command('notify:low-stock --threshold=5')->dailyAt('08:00');
        $schedule->command('notify:due-invoices --days=0')->dailyAt('09:00');
        $schedule->command('billing:generate-periodic-invoices')->dailyAt('01:00');
        $schedule->command('tenants:sync-feature-flags')->dailyAt('02:00');
        $schedule->command('treasury:generate')->dailyAt('03:00');
        // Après les travaux de nuit, avant l'ouverture : un client qui bascule
        // en lecture seule doit le découvrir par l'email de relance, pas en
        // tapant sa première facture de la journée.
        $schedule->command('subscriptions:check')->dailyAt('07:00');
        // Après subscriptions:check : les statuts sont à jour, et un tenant qui
        // vient de basculer en impayé a déjà sa facture, émise quinze jours
        // plus tôt.
        $schedule->command('subscriptions:invoice')->dailyAt('07:15');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
