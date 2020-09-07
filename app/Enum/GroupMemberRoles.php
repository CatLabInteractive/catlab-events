<?php

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