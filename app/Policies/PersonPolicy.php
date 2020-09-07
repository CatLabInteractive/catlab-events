<?php

namespace App\Policies;
use App\Models\Person;
use App\Models\User;
use App\Models\Venue;

/**
 * Class VenuePolicy
 * @package App\Policies
 */
class PersonPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function index(User $user)
    {
        return true;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * @param User $user
     * @param Venue $venue
     * @return bool
     */
    public function view(User $user, Person $venue)
    {
        return true;
    }

    /**
     * @param User $user
     * @param Venue $venue
     * @return bool
     */
    public function destroy(User $user, Person $venue)
    {
        return true;
    }

    /**
     * @param User $user
     * @param Venue $venue
     * @return bool
     */
    public function edit(User $user, Person $venue)
    {
        return true;
    }
}