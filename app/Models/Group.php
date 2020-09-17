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

namespace App\Models;

use App\Exceptions\Groups\GroupMergeEventOverlapException;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Group
 * @package App\Models
 */
class Group extends \CatLab\Charon\Laravel\Database\Model implements EuklesModel
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function members()
    {
        return $this->hasMany(GroupMember::class)->with('user');
    }

    /**
     * Check if a user is a member of this group and return the groupmember.
     * @param User $user
     * @return GroupMember
     */
    public function getGroupMember(User $user)
    {
        $result = $this->members->filter(
            function($member) use ($user) {
                return $member->user && $member->user->id === $user->id;
            }
        );
        if (count($result) > 0) {
            return $result->first();
        }
        return null;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isMember(User $user)
    {
        return $this->getGroupMember($user) !== null;
    }

    /**
     * Check if a given user is an administrator.
     * @param $user
     * @return bool
     */
    public function isAdmin($user)
    {
        $myRole = $this->getGroupMember($user);
        if (!$myRole) {
            return false;
        }

        if (!$myRole->isAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        if ($this->id) {
            return action('GroupController@show', [ $this->id ]);
        } else {
            return null;
        }
    }

    /**
     * @return array[]
     */
    public function getEuklesId()
    {
        return $this->id;
    }

    /**
     * @return array[]
     */
    public function getEuklesAttributes()
    {
        return [
            'name' => $this->name,
            'url' => $this->getUrl()
        ];
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'group';
    }


    /**
     * Check if two groups can merge and throw an exception if not.
     * @param Group $group
     * @throws GroupMergeEventOverlapException
     */
    public function checkMerge(Group $group)
    {
        // Look for any overlapping events
        foreach ($this->orders()->accepted()->get() as $order1) {
            foreach ($group->orders()->accepted()->get() as $order2) {
                if ($order1->event->equals($order2->event)) {
                    throw GroupMergeEventOverlapException::make($order1->event);
                }
            }
        }
    }

    /**
     * Merge group with other group.
     * @param Group $group
     * @return $this
     * @throws GroupMergeEventOverlapException
     * @throws \Exception
     */
    public function merge(Group $group)
    {
        if ($this->equals($group)) {
            return $this;
        }

        // check if the merge is allowed.
        $this->checkMerge($group);

        // Decide which group to keep
        if ($this->id < $group->id) {
            $sourceGroup = $group;
            $targetGroup = $this;
        } else {
            $sourceGroup = $this;
            $targetGroup = $group;
        }

        // Name of the group should be the group that was selected (so this one)
        $targetGroup->name = $this->name;
        $targetGroup->save();

        // Update all members
        foreach ($sourceGroup->members as $member) {
            /** @var GroupMember $member */
            if ($member->user === null || !$targetGroup->isMember($member->user)) {
                $member->group()->associate($targetGroup);
                $member->save();
            } else {
                $member->delete();
            }
        }

        // Update all scores
        foreach ($sourceGroup->scores as $score) {
            /** @var Score $score */
            $score->group()->associate($targetGroup);
            $score->save();
        }

        // Update all tickets
        foreach ($sourceGroup->orders as $order) {
            /** @var Order $order */
            $order->group()->associate($targetGroup);
            $order->save();
        }

        // Remove the source group
        $sourceGroup->delete();

        // Return the resulting group.
        return $targetGroup;
    }

    /**
     * @param $query
     * @param $name
     */
    public function scopeSimilarName($query, $name)
    {
        $query->where('name', '=', $name);
    }

    /**
     * @param Group $group
     * @return bool
     */
    public function equals(Group $group)
    {
        return $this->id === $group->id;
    }
}
