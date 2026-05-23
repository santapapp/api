<?php

namespace App\Events;

use App\Models\DiningTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DiningTable $diningTable;

    /**
     * Create a new event instance.
     */
    public function __construct(DiningTable $diningTable)
    {
        $this->diningTable = $diningTable;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('org.' . $this->diningTable->organization_id),
            new PrivateChannel('cashier.' . $this->diningTable->organization_id),
            new PrivateChannel('table.' . $this->diningTable->organization_id . '.' . $this->diningTable->id),
        ];
    }
}
