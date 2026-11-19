<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventEmail;
use App\Models\User;

/**
 * The mails of an event are chosen by the admins of its organisation. Only
 * an event's own choice can be removed.
 *
 * @package App\Policies
 */
class EventMailPolicy
{
    public function index(User $user, Event $event)
    {
        return $event->organisation && $event->organisation->isAdmin($user);
    }

    public function view(User $user, EventEmail $mail)
    {
        return $this->isAdmin($user, $mail);
    }

    public function edit(User $user, EventEmail $mail)
    {
        return $this->isAdmin($user, $mail);
    }

    public function destroy(User $user, EventEmail $mail)
    {
        return $mail->exists && $this->isAdmin($user, $mail);
    }

    protected function isAdmin(User $user, EventEmail $mail)
    {
        return $mail->event && $this->index($user, $mail->event);
    }
}
