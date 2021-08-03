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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * @var Organisation
     */
    private $activeOrganisation;

    public static function boot()
    {
        parent::boot();

        // The first user who registers gets to be an admin.
        self::creating(function(User $user) {
            if (User::count() === 0) {
                $user->admin = 1;
            }
        });
    }

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
            'name' => $this->name
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

    /**
     * @return Order[]
     */
    public function orders()
    {
        return Order
            ::where(function($queryBuilder) {
                $groups = $this->groups()->pluck('group_id');

                $queryBuilder->whereIn('group_id', $groups)
                    ->orWhere('user_id', '=', $this->id);
            });
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
        if ($this->catlab_id) {
            return $this->catlab_id;
        } else {
            return $this->id;
        }
    }

    /**
     * @return string|null
     */
    public function getUrl()
    {
        if ($accountsUrl = config('services.catlab.url')) {
            return $accountsUrl . 'admin/users/' . $this->id;
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        if ($this->username) {
            return $this->username;
        } else {
            return 'User ' . $this->id;
        }
    }

    /**
     * @return array[]
     */
    public function getEuklesAttributes()
    {
        return [
            'name' => $this->getDisplayName(),
            'username' => $this->getDisplayName(),
            'email' => $this->email,
            'event_id' => $this->id,
            'url' => $this->getUrl()
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
