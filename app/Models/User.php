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

use CatLab\Eukles\Client\Interfaces\EuklesModel;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

/**
 * Class User
 * @package App\Models
 */
class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    EuklesModel
{
    use Authenticatable, Authorizable;
    use Notifiable;

    /**
     * @var Organisation
     */
    private $activeOrganisation;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function organisations()
    {
        return $this->belongsToMany(Organisation::class, 'user_organisation')
            ->withTimestamps();
    }

    /**
     * Return the currently active organisation (or create one if none exists)
     * @return Organisation
     */
    public function getActiveOrganisation()
    {
        if (isset($this->activeOrganisation)) {
            return $this->activeOrganisation;
        }

        if (request()->query('switchOrganisations')) {
            $activeOrganisationId = request()->get('switchOrganisations');
            session([ 'activeOrganisationId' => $activeOrganisationId ]);
        } else {
            $activeOrganisationId = session('activeOrganisationId');
        }

        if ($activeOrganisationId) {
            $organisation = $this->organisations()
                ->where('organisation_id', '=', $activeOrganisationId)
                ->first();

            if ($organisation) {
                $this->activeOrganisation = $organisation;
                return $organisation;
            }
        }

        $organisation = $this->organisations()->first();
        if (!$organisation) {
            // must create organisation
            $organisation = $this->createFirstOrganisation();
        }

        session([ 'activeOrganisationId' => $organisation->id ]);
        return $organisation;

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')->withTimestamps();
    }

    /**
     * @return Organisation
     */
    protected function createFirstOrganisation()
    {
        $organisation = new Organisation([
            'name' => $this->username
        ]);

        $organisation->save();

        $this->organisations()->attach(
            $organisation,
            [
                'role' => Organisation::ROLE_ADMIN
            ]
        );

        return $organisation;
    }

    public function orders()
    {
        $groups = $this->groups()->pluck('group_id');
        return Order::whereIn('group_id', $groups);
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->admin;
    }

    /**
     * @param $user
     * @return bool
     */
    public function equals($user)
    {
        return $this->id === $user->id;
    }

    /**
     * @return array[]
     */
    public function getEuklesId()
    {
        return $this->catlab_id;
    }

    /**
     * @return array[]
     */
    public function getEuklesAttributes()
    {
        return [
            'username' => $this->username,
            'email' => $this->email,
            'event_id' => $this->id
        ];
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'user';
    }
}
