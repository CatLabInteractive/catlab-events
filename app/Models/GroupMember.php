<?php

namespace App\Models;

use App\Enum\GroupMemberRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GroupMember
 * @package App\Models
 */
class GroupMember extends Model
{
    protected $fillable = [
        'role',
        'name',
        'email'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->user) {
            return $this->user->username;
        }
        return $this->name;
    }

    /**
     * @return string
     */
    public function getRoleName()
    {
        return GroupMemberRoles::toString($this->role);
    }

    /**
     * @param $roleString
     */
    public function setRoleName($roleString)
    {
        $role = GroupMemberRoles::fromString($roleString);
        $this->role = $role;
    }

    public function isAdmin()
    {
        return $this->role === GroupMemberRoles::ADMIN;
    }
}