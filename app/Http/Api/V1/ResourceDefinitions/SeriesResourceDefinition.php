<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\Series;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class SeriesResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class SeriesResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Series::class);

        $this->identifier('id');

        $this->field('slug')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('name')
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('teaser')
            ->visible()
            ->writeable(true, true);

        $this->field('description')
            ->visible()
            ->writeable(true, true);

        $this->field('youtube_url')
            ->string()
            ->visible()
            ->writeable(true, true);

        $this->relationship('youtubeThumbnail', AssetResourceDefinition::class)
            ->display('youtube_thumbnail')
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->field('active')
            ->bool()
            ->visible(true, true)
            ->writeable(true, true);

        $this->relationship('logo', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();

        $this->relationship('header', AssetResourceDefinition::class)
            ->one()
            ->visible()
            ->expanded()
            ->linkable();
    }
}