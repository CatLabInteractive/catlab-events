<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

/**
 * Class EventPolicy
 * @package App\Policies
 */
class OrganisationPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function index(User $user)
    {
        return false;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return false;
    }

    /**
     * @param User $user
     * @param Story $story
     * @return bool
     */
    public function view(User $user, Story $story)
    {
        return false;
    }

    /**
     * @param User $user
     * @param Story $story
     * @return bool
     */
    public function destroy(User $user, Story $story)
    {
        return false;
    }

    public function edit(User $user, Story $story)
    {
        return false;
    }
}