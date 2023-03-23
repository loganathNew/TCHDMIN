<?php

namespace App\Listeners;

use App\Events\ActionLog;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ActionLogListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        // \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'),0,'error');
    }

    /**
     * Handle the event.
     *
     * @param  ActionLog  $event
     * @return void
     */
    public function handle(ActionLog $event)
    {
        $records = $event->data;
        \Log::info($records);
    }
}
