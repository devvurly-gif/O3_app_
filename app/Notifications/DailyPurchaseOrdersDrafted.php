<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyPurchaseOrdersDrafted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{supplier: \App\Models\ThirdPartner, document: \App\Models\DocumentHeader, lines: int}> $created
     * @param array<int, array{product: \App\Models\Product, qty: float}> $unassigned
     */
    public function __construct(
        private array $created,
        private array $unassigned,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->created);

        $message = (new MailMessage)
            ->subject("Brouillons de BCF du jour — {$count} fournisseur(s)")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$count} bon(s) de commande fournisseur ont été préparés en brouillon, à relire et confirmer vous-même dans O3 avant tout envoi :");

        foreach ($this->created as $c) {
            $message->line("• {$c['document']->reference} — {$c['supplier']->tp_title} ({$c['lines']} ligne(s))");
        }

        if (!empty($this->unassigned)) {
            $message->line('');
            $message->line(count($this->unassigned) . " produit(s) vendu(s) sans fournisseur connu — à assigner manuellement :");
            foreach (array_slice($this->unassigned, 0, 10) as $u) {
                $message->line("• {$u['product']->p_sku} — {$u['product']->p_title} (vendu : {$u['qty']})");
            }
        }

        return $message
            ->action('Voir les commandes fournisseur', url('/achats/documents'))
            ->line('Ces documents sont en brouillon : rien n\'a été envoyé au fournisseur.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'daily_po_drafted',
            'count'      => count($this->created),
            'orders'     => array_map(fn ($c) => [
                'reference' => $c['document']->reference,
                'supplier'  => $c['supplier']->tp_title,
                'lines'     => $c['lines'],
            ], $this->created),
            'unassigned' => array_map(fn ($u) => [
                'sku'   => $u['product']->p_sku,
                'title' => $u['product']->p_title,
                'qty'   => $u['qty'],
            ], $this->unassigned),
        ];
    }
}
