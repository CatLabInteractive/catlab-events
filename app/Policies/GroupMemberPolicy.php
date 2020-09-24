<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

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
