<?php

namespace App\Http\Api\V1\ResourceDefinitions\Groups;

use App\Http\Api\V1\ResourceDefinitions\ScoreResourceDefinition;
use App\Http\Api\V1\Validators\GroupValidator;
use App\Models\Group;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class GroupResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class GroupResourceDefinition extends ResourceDefinition
{
    /**
     * StoryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(Group::class);

        // Identifier
        $this->identifier('id');

        // Name
        $this->field('name')
            ->visible(true)
            ->filterable()
            ->writeable(true, true)
            ->required()
            ->string()
            ->min(3)
            ->max(50)
        ;

        $this->relationship('members', GroupMemberResourceDefinition::class)
            ->writeable(true, true)
            ->visible()
            ->expanded()
            ->url('api/v1/groups/{model.id}/members')
        ;

        $this->relationship('scores', ScoreResourceDefinition::class)
            ->visible()
            ->expanded();

        $this->validator(new GroupValidator());
    }
}