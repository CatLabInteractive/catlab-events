<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

/**
 * Class GroupMemberPolicy
 * @package App\Policies
 */
class GroupMemberPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function index(User $user, Group $group)
    {
        return true;
    }

    /**
     * @param User $user
     * @param Group $group
     * @return bool
     */
    public function create(User $user, Group $group)
    {
        if (!$group->isMember($user)) {
            return false;
        }

        $member = $group->getGroupMember($user);
        return $this->isAdmin($user, $member);
    }

    /**
     * @param User $user
     * @param GroupMember $member
     * @return bool
     */
    public function view(User $user, GroupMember $member)
    {
        return $member->group->isMember($user);
    }

    /**
     * @param User $user
     * @param GroupMember $member
     * @return bool
     */
    public function destroy(User $user, GroupMember $member)
    {
        // Same as editing
        return $this->edit($user, $member);
    }

    /**
     * @param User $user
     * @param GroupMember $member
     * @return bool
     */
    public function edit(User $user, GroupMember $member)
    {
        if (!$this->isAdmin($user, $member)) {
            return false;
        }

        // Can remove anonymous users
        if (!$member->user) {
            return true;
        }

        // Cannot remove yourself
        if ($member->user->id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * @param User $user
     * @param GroupMember $member
     * @return bool
     */
    protected function isAdmin(User $user, GroupMember $member)
    {
        /** @var Group $group */
        $group = $member->group;
        return $group->isAdmin($user);
    }
}