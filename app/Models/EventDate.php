<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2021 Thijs Van der Schaeghe
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

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class EventDate extends Model implements EuklesModel
{
    use SoftDeletes;

    protected $fillable = [
        'startDate',
        'endDate',
        'doorsDate'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'startDate',
        'endDate',
        'doorsDate'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * @return mixed
     */
    public function getNameAttribute()
    {
        return $this->startDate->format('D d/m/Y H:i');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ticketCategories()
    {
        return $this->belongsToMany(
            TicketCategory::class,
            'event_ticket_categories_dates',
            'event_date_id',
            'event_ticket_category_id',
        );
    }

    /**
     * @return Builder
     */
    public function orders()
    {
        return Order::whereIn('ticket_category_id', $this->ticketCategories->pluck('id'));
    }

    /**
     * @return false
     */
    public function isSoldOut()
    {
        return $this->countAvailableTickets() <= 0;
    }

    /**
     * @return bool
     */
    public function isFinished()
    {
        return $this->endDate < (new DateTime());
    }

    /**
     * @return bool
     */
    public function hasFiniteTickets()
    {
        return $this->max_tickets > 0;
    }

    /**
     * @return int|null
     */
    public function countAvailableTickets($includePendingSales = true)
    {
        if ($this->max_tickets) {
            return max(0, $this->max_tickets - $this->countSoldTickets($includePendingSales));
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function countSoldTickets($includePendingSales = true)
    {
        if ($includePendingSales) {
            $states = [Order::STATE_ACCEPTED, Order::STATE_PENDING];
        } else {
            $states = [Order::STATE_ACCEPTED ];
        }

        return $this
            ->orders()
            ->whereIn('state', $states)
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function attendees()
    {
        $orderGroupIds = $this->orders()
            ->accepted()
            ->whereNotNull('group_id')
            ->pluck('group_id')
            ->toArray();

        if (count($orderGroupIds) > 0) {
            return Group
                ::whereIn('id', $orderGroupIds)
                ->orderByRaw("FIELD(id, " . implode(',', $orderGroupIds) . ")");
        } else {
            return Group
                ::whereIn('id', $orderGroupIds);
        }
    }

    /**
     * Remove all existing scores.
     */
    public function dumpScores()
    {
        // Delete any existing
        $this->scores()->delete();
    }

    /**
     * @param Group $group
     * @param $position
     * @param $name
     * @param $point
     */
    public function setScore($position, $name, $point, Group $group = null)
    {
        // Create new
        $score = new Score();

        if ($group) {
            $score->group()->associate($group);
        }

        $score->eventDate()->associate($this);
        $score->event()->associate($this->event);

        $score->position = $position;
        $score->score = $point;
        $score->name = $name;

        $score->save();
    }

    /**
     * @return bool
     */
    public function hasScores()
    {
        return $this->scores()->count() > 0;
    }

    /**
     * @return array
     */
    public function getEuklesAttributes()
    {
        return [
            'start' => $this->startDate ? $this->startDate->format('c') : null,
            'end' => $this->endDate ? $this->endDate->format('c') : null,
            'doors' => $this->doors ? $this->doors->format('c') : null
        ];
    }

    /**
     * @return int
     */
    public function getEuklesId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'event-date';
    }
}
