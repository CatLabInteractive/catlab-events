<?php

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\LiveStream;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class SeriesResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class LiveStreamResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(LiveStream::class);

        $this->identifier('id');

        $this->field('token')
            ->string()
            ->visible();

        $this->field('title')
            ->string()
            ->visible(true)
            ->writeable()
            ->required();

        $this->field('twitch_key')
            ->display('twitch_channel')
            ->string()
            ->visible()
            ->writeable();

        /*
        $this->field('mixer_key')
            ->display('mixer_channel')
            ->string()
            ->visible()
            ->writeable();
        */

        $this->field('streaming')
            ->bool()
            ->visible(true, true)
            ->writeable(false, true);

        $this->field('livestreamUrl')
            ->string()
            ->visible(true, true);
    }
}
