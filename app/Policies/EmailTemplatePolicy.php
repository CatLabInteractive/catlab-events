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

use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Models\User;

/**
 * The library of named mail templates is managed by the admins of the
 * organisation it belongs to.
 *
 * @package App\Policies
 */
class EmailTemplatePolicy
{
    public function index(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    public function create(User $user, Organisation $organisation)
    {
        return $organisation->isAdmin($user);
    }

    public function view(User $user, EmailTemplate $template)
    {
        return $this->isAdmin($user, $template);
    }

    public function edit(User $user, EmailTemplate $template)
    {
        return $this->isAdmin($user, $template);
    }

    public function destroy(User $user, EmailTemplate $template)
    {
        return $this->isAdmin($user, $template);
    }

    protected function isAdmin(User $user, EmailTemplate $template)
    {
        return $template->organisation && $template->organisation->isAdmin($user);
    }
}
