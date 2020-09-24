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
