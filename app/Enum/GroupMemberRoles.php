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

namespace App\Enum;

/**
 * Class GroupMemberRoles
 * @package App\Enum
 */
class GroupMemberRoles
{
    const MEMBER = 0;
    const ADMIN = 1;

    /**
     * Return the string description for this role.
     * @param $role
     * @return string
     */
    public static function toString($role)
    {
        switch ($role) {
            case self::MEMBER:
                return 'member';

            case self::ADMIN:
                return 'admin';
        }
    }

    /**
     * @param $roleString
     * @return int
     */
    public static function fromString($roleString)
    {
        switch ($roleString) {
            case 'admin':
                return self::ADMIN;

            case 'member':
            default:
                return self::MEMBER;
        }
    }
}
