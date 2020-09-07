<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Story;
use App\Models\User;

/**
 * Class EventPolicy
 * @package App\Policies
 */
class CompetitionPolicy
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
     * @param Competition $event
     * @return bool
     */
    public function view(User $user, Competition $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Competition $event
     * @return bool
     */
    public function destroy(User $user, Competition $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Competition $event
     * @return bool
     */
    public function edit(User $user, Competition $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Competition $event
     * @return bool
     */
    protected function isAdmin(User $user, Competition $event)
    {
        return $event->organisation->isAdmin($user);
    }
}