<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Story;
use App\Models\User;

/**
 * Class EventPolicy
 * @package App\Policies
 */
class EventPolicy
{
    /**
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function index(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function create(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function view(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function destroy(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function edit(User $user, Event $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Event $event
     * @return bool
     */
    protected function isAdmin(User $user, Event $event)
    {
        return $event->organisation->isAdmin($user);
    }
}