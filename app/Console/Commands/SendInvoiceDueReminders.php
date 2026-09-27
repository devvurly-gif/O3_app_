<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachTenant;
use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Console\Command;

class SendInvoiceDueReminders extends Command
{
    use RunsForEachTenant;

    protected $signature = 'notify:due-invoices
        {--days=0 : Days past due date (0 = today)}
        {--tenant= : Only remind this tenant (default: every tenant in good standing)}';

    protected $description = 'Send email reminders for overdue invoices';

    public function handle(): int
    {
        return $this->runForEachTenant(
            fn () => $this->remind((int) $this->option('days')),
            writes: false,
            only: $this->option('tenant'),
        );
    }

    private function remind(int $days): int
    {
        $cutoffDate = now()->subDays($days);

        $overdueDocuments = DocumentHeader::with(['thirdPartner', 'footer'])
            ->whereIn('document_type', ['InvoiceSale', 'InvoicePurchase'])
            ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $cutoffDate)
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
            ->orderBy('due_at')
            ->get();

        if ($overdueDocuments->isEmpty()) {
            $this->info('No overdue invoices found.');
            return self::SUCCESS;
        }

        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No active admin/manager users to notify.');
            return self::SUCCESS;
        }

        $notification = new InvoiceDueReminder($overdueDocuments);

        foreach ($recipients as $user) {
            $user->notify($notification);
        }

        $this->info("Due reminder sent to {$recipients->count()} user(s) for {$overdueDocuments->count()} invoice(s).");

        return self::SUCCESS;
    }
}
