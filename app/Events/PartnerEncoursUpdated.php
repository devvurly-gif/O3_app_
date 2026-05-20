<?php

namespace App\Events;

use App\Models\ThirdPartner;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartnerEncoursUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ThirdPartner $partner,
        public float $encours,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("partner.{$this->partner->id}")];
    }

    public function broadcastAs(): string
    {
        return 'encours-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'partner_id' => $this->partner->id,
            'encours_actuel' => $this->encours,
            'seuil_credit' => $this->partner->seuil_credit,
        ];
    }
}
