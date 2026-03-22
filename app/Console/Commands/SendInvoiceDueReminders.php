<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Console\Command;

class SendInvoiceDueReminders extends Command
{
    protected $signature = 'notify:due-invoices {--days=0 : Days past due date (0 = today)}';

    protected $description = 'Send email reminders for overdue invoices';

    public function handle(): int
    {
        $days = (int) $this->option('days');
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
