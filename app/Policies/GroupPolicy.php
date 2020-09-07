<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Story;
use App\Models\User;

/**
 * Class GroupPolicy
 * @package App\Policies
 */
class GroupPolicy
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
     * @param Group $group
     * @return bool
     */
    public function view(User $user, Group $group)
    {
        return true;
    }

    /**
     * @param User $user
     * @param Group $group
     * @return bool
     */
    public function destroy(User $user, Group $group)
    {
        return false;
    }

    /**
     * @param User $user
     * @param Group $group
     * @return bool
     */
    public function edit(User $user, Group $group)
    {
        return $user->isAdmin() || $group->isAdmin($user);
    }

    /**
     * @param User $user
     * @param Group $group
     * @return bool
     */
    public function merge(User $user, Group $group)
    {
        return $user->isAdmin() || $group->isAdmin($user);
    }
}