<?php

namespace App\Exceptions\Groups;

use App\Exceptions\Exception;

/**
 * Class GroupMergeEventOverlapException
 */
class GroupMergeEventOverlapException extends Exception
{
    /**
     * @var $event
     */
    public $event;

    /**
     * @param \App\Models\Event $event
     * @return GroupMergeEventOverlapException
     */
    public static function make(\App\Models\Event $event)
    {
        $e = new self("Group merge: event overlap detected: both groups attended " . $event->name);
        $e->event = $event;

        return $e;
    }
}
