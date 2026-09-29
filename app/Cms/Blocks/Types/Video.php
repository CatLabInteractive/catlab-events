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

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockContext;
use App\Cms\Blocks\BlockType;

/**
 * An embedded YouTube video, always played from youtube-nocookie.com.
 *
 * Class Video
 * @package App\Cms\Blocks\Types
 */
class Video extends BlockType
{
    public function type(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return 'Video (YouTube)';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'youtube_url' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!is_string($value) || self::youtubeId($value) === null) {
                        $fail('Het veld :attribute moet een YouTube-link zijn.');
                    }
                },
            ],
            'caption' => [ 'nullable', 'string', 'max:300' ],
        ];
    }

    public function defaults(): array
    {
        return [ 'title' => '', 'youtube_url' => '', 'caption' => '' ];
    }

    /**
     * The embed URL is built from the video id at render time; the stored
     * URL is never printed.
     */
    public function prepare(array $data, BlockContext $context): ?array
    {
        $id = is_string($data['youtube_url'] ?? null) ? self::youtubeId($data['youtube_url']) : null;
        if ($id === null) {
            return null;
        }

        $data['embed_url'] = 'https://www.youtube-nocookie.com/embed/' . $id;

        return $data;
    }

    /**
     * The 11 character video id of a youtube.com / youtu.be /
     * youtube-nocookie.com URL, or null.
     * @param string $url
     * @return string|null
     */
    public static function youtubeId(string $url): ?string
    {
        $pattern = '~^https?://(?:(?:www\.|m\.)?youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)'
            . '|(?:www\.)?youtube-nocookie\.com/embed/'
            . '|youtu\.be/)([A-Za-z0-9_\-]{11})(?:[?&#/].*)?$~';

        if (preg_match($pattern, trim($url), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
