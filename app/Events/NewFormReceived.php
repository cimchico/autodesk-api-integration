<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewFormReceived implements ShouldQueue, ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $form;

    /**
     * Create a new event instance.
     */
    public function __construct($form)
    {
        //
        $this->form = $form;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return new Channel('forms');
       
    }

    public function broadcastAs()
    {
        return 'form.received';
    }
}
