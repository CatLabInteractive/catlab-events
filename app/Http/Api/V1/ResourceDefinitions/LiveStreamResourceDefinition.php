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

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Http\Api\V1\ResourceDefinitions\Events\EventResourceDefinition;
use App\Http\Api\V1\Validators\LiveStreamValidator;
use App\Models\LiveStream;

/**
 * Class SeriesResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class LiveStreamResourceDefinition extends BaseResourceDefinition
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

        $this->relationship('event', EventResourceDefinition::class)
            ->one()
            ->visible()
            ->linkable(true, true)
            ->expanded();

        $this->field('twitch_key')
            ->display('twitch_channel')
            ->string()
            ->visible()
            ->writeable();

        $this->field('youtube_video')
            ->string()
            ->visible()
            ->writeable();

        $this->field('redirect_uri')
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

        $this->field('rocketchat_channel')
            ->string()
            ->visible()
            ->writeable();

        $this->field('deadsimple_chat_url')
            ->string()
            ->visible()
            ->writeable();

        $this->field('show_footer')
            ->bool()
            ->visible()
            ->writeable(false, true);

        $this->addLanguageField();

        $this->validator(new LiveStreamValidator());
    }
}
