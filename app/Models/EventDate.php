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
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class EventDate extends Model
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
        return $this->countAvailableTickets() === 0;
    }

    /**
     * @return bool
     */
    public function isFinished()
    {
        return $this->endDate < (new DateTime());
    }

    /**
     * @return int|null
     */
    public function countAvailableTickets()
    {
        if ($this->max_tickets) {
            return $this->max_tickets - $this->countSoldTickets();
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function countSoldTickets()
    {
        return $this
            ->orders()
            ->whereIn('state', [ Order::STATE_ACCEPTED, Order::STATE_PENDING ])
            ->count();
    }
}
