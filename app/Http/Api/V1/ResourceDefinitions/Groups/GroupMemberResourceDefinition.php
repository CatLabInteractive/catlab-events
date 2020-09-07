<?php

namespace App\Http\Api\V1\ResourceDefinitions\Groups;

use App\Models\GroupMember;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class GroupMemberResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class GroupMemberResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(GroupMember::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, false)
            ->required()
            ->string()
            ->min(3);

        // Email address
        $this->field('email')
            ->writeable(true, false)
            ->required()
            ->string();

        // Role
        $this->field('roleName')
            ->display('role')
            ->visible(true)
            ->writeable(true, true)
            ->enum([ 'member', 'admin' ]);
    }
}