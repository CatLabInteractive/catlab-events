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
use App\Models\Event;

/**
 * Live list of the organisation's published upcoming events (the
 * EventController@calendar query), optionally only packages or events.
 *
 * Class UpcomingEvents
 * @package App\Cms\Blocks\Types
 */
class UpcomingEvents extends BlockType
{
    public function type(): string
    {
        return 'upcoming_events';
    }

    public function label(): string
    {
        return 'Komende evenementen';
    }

    public function rules(): array
    {
        return [
            'title' => [ 'nullable', 'string', 'max:120' ],
            'intro' => [ 'nullable', 'string', 'max:500' ],
            'limit' => [ 'required', 'integer', 'between:1,12' ],
            'event_type' => [ 'required', 'in:event,package,all' ],
            'show_sold_out' => [ 'nullable', 'boolean' ],
            'empty_text' => [ 'nullable', 'string', 'max:300' ],
        ];
    }

    public function defaults(): array
    {
        return [
            'title' => '',
            'intro' => '',
            'limit' => 4,
            'event_type' => 'all',
            'show_sold_out' => true,
            'empty_text' => '',
        ];
    }

    public function prepare(array $data, BlockContext $context): ?array
    {
        $limit = max(1, min(12, (int) ($data['limit'] ?? 4)));
        $showSoldOut = filter_var($data['show_sold_out'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $query = $context->organisation
            ->events()
            ->upcoming()
            ->published()
            ->orderByStartDate()
            ->with([ 'eventDates', 'series', 'venue' ]);

        $eventType = $data['event_type'] ?? 'all';
        if (in_array($eventType, [ Event::TYPE_EVENT, Event::TYPE_PACKAGE ], true)) {
            $query->where('events.event_type', '=', $eventType);
        }

        if ($showSoldOut) {
            $events = $query->limit($limit)->get();
        } else {
            $events = $query->get()
                ->reject(function (Event $event) {
                    return $event->isSoldOut();
                })
                ->take($limit)
                ->values();
        }

        $data['events'] = $events;

        return $data;
    }
}
