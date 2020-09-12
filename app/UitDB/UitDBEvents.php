<?php


namespace App\UitDB;

use App\Models\Event;

/**
 * Class UitDBEvents
 * @package App\UitDB
 */
class UitDBEvents
{
    /**
     * Upload an event to the uitdb and return the event id.
     * @param Event $event
     * @return string
     */
    public function upload(Event $event)
    {
        return 'abcdef';
    }
}
