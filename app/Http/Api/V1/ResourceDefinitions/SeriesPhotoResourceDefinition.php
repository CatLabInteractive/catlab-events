<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\SeriesPhoto;

/**
 * A photo in a series' hero slideshow. Lower sort numbers come first.
 */
class SeriesPhotoResourceDefinition extends BaseResourceDefinition
{
    public function __construct()
    {
        parent::__construct(SeriesPhoto::class);

        $this->identifier('id');

        $this->relationship('photo', AssetResourceDefinition::class)
            ->one()
            ->visible(true)
            ->expanded()
            ->linkable();

        $this->field('sort')
            ->number()
            ->sortable()
            ->visible(true)
            ->writeable(true, true);
    }
}
