<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ActionLog
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        // if (!empty($records)) {
        //     $msg = ""; 
        //     foreach ($records as $k => $r) {
        //         $result = [];
        //         $module = $r['module'];
        //         $action = $r['action'];
        //         $record = $r['record'];
        //         foreach ($record as $key => $value) {
        //             $result[] = "[" . $key . ":" . $value . "]";
        //         }
        //         $msg .= "\r".$module . "-" . $action."\r".implode("-", $result);
        //     }
        // }
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
