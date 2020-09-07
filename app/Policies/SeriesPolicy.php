<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Series;
use App\Models\Story;
use App\Models\User;

/**
 * Class SeriesPolicy
 * @package App\Policies
 */
class SeriesPolicy
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
     * @param Series $event
     * @return bool
     */
    public function view(User $user, Series $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Series $event
     * @return bool
     */
    public function destroy(User $user, Series $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Series $event
     * @return bool
     */
    public function edit(User $user, Series $event)
    {
        return $this->isAdmin($user, $event);
    }

    /**
     * @param User $user
     * @param Series $event
     * @return bool
     */
    protected function isAdmin(User $user, Series $event)
    {
        return $event->organisation->isAdmin($user);
    }
}