<?php

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\Organisation;
use App\Models\Person;
use App\Models\Series;
use App\Models\User;
use App\Models\Venue;

/**
 * Class VenuePolicy
 * @package App\Policies
 */
class LiveStreamPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function index(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    /**
     * @param User $user
     * @param LiveStream $model
     * @return bool
     */
    public function view(User $user, LiveStream $model)
    {
        return $this->isAdmin($user, $model);
    }

    /**
     * @param User $user
     * @param LiveStream $model
     * @return bool
     */
    public function destroy(User $user, LiveStream $model)
    {
        return $this->isAdmin($user, $model);
    }

    /**
     * @param User $user
     * @param LiveStream $model
     * @return bool
     */
    public function edit(User $user, LiveStream $model)
    {
        return $this->isAdmin($user, $model);
    }

    /**
     * @param User $user
     * @param LiveStream $model
     * @return mixed
     */
    protected function isAdmin(User $user, LiveStream $model)
    {
        return $model->organisation->isAdmin($user);
    }
}
