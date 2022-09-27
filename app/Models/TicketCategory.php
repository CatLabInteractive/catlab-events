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

namespace App\Models;

use App\Http\Controllers\EventController;
use App\Tools\TicketPriceCalculator;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TicketCategory
 * @package App\Models
 */
class TicketCategory extends Model implements EuklesModel
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $table = 'event_ticket_categories';

    /**
     * @var
     */
    protected $availableError = null;

    /**
     * @var TicketPriceCalculator
     */
    private $ticketPriceCalculator;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function eventDates()
    {
        return $this->belongsToMany(
            EventDate::class,
            'event_ticket_categories_dates',
            'event_ticket_category_id',
            'event_date_id'
        );
    }

    /**
     * @return array
     */
    public function getDateRangeForDisplay()
    {
        $min = $this->eventDates->pluck('startDate')->min();
        $max = $this->eventDates->pluck('endDate')->max();

        if ($min->format('Y-m-d') === $max->format('Y-m-d')) {
            return [ $min ];
        } else {
            return [ $min, $max ];
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param Group $group
     * @return Order
     */
    public function createOrder(Group $group = null)
    {
        $order = new Order();

        $order->event()->associate($this->event);

        if ($group) {
            $order->group()->associate($group);
        }

        $order->ticketCategory()->associate($this);
        $order->user()->associate(\Auth::getUser());

        return $order;
    }

    /**
     * @return TicketPriceCalculator
     */
    public function getTicketPriceCalculator()
    {
        if (!isset($this->ticketPriceCalculator)) {
            $this->ticketPriceCalculator = new TicketPriceCalculator($this);
        }
        return $this->ticketPriceCalculator;
    }

    /**
     * @return string
     */
    public function getFormattedTotalPrice()
    {
        return $this->getTicketPriceCalculator()->getFormattedTotalPrice();
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
        return $this->getTicketPriceCalculator()->getTotalPrice();
    }

    /**
     * @return null|int
     */
    public function countAvailableTickets()
    {
        $eventAvailable = $this->countAvailableEventDateTickets();
        if ($eventAvailable !== null && $eventAvailable <= 0) {
            return 0;
        }

        if ($this->max_tickets) {
            if ($eventAvailable !== null) {
                return min($eventAvailable, $this->max_tickets - $this->countSoldTickets());
            } else {
                return $this->max_tickets - $this->countSoldTickets();
            }
        } elseif ($eventAvailable !== null) {
            return $eventAvailable;
        } else {
            return null;
        }
    }

    /**
     * @return int|null
     */
    public function countAvailableEventDateTickets()
    {
        if (count($this->eventDates) === 0) {
            return null;
        }

        return $this->eventDates
            ->map(function(EventDate $eventDate) {
                return $eventDate->countAvailableTickets();
            })->min();
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

    /**
     * @return int
     */
    public function countSoldEventDateTickets()
    {
        if (count($this->eventDates) === 0) {
            return $this->countSoldTickets();
        }

        $total = 0;
        foreach ($this->eventDates as $eventDate) {
            /** @var EventDate $eventDate */
            $total += $eventDate->countSoldTickets();
        }
        return $total;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isAvailable()
    {
        if (!$this->event->canRegister()) {
            return false;
        }

        return $this->getAvailableError() === false;
    }

    /**
     * Same as isAvailable, but returns true for tickets
     * that are not on sale yet.
     */
    public function willBecomeAvailable()
    {
        $available = $this->countAvailableTickets();
        if ($available !== null && $available <= 0) {
            // sold out
            return false;
        }

        elseif ($this->end_date && ($this->end_date < new DateTime())) {
            return false;
        }

        else {
            return true;
        }
    }

    /**
     * @return array|null
     * @throws \Exception
     */
    public function getAvailableError()
    {
        if ($this->event->isFinished()) {
            return [ 'Te laat' ];
        }

        if (!isset($this->availableError)) {

            $available = $this->countAvailableTickets();
            if (
                $available !== null &&
                $available <= 0 &&
                ! (! (\Auth::user() && \Auth::user()->can('buyWhenSoldOut', $this)))
            ) {
                $this->availableError = [ 'Uitverkocht.' ];
            }

            elseif (
                $this->start_date && ($this->start_date > new DateTime()) &&
                ! (\Auth::user() && \Auth::user()->can('buyBeforeStartDate', $this)) &&
                !EventController::hasValidWaitingListToken($this->event)
            ) {
                $this->availableError = [ 'Vanaf %s', $this->start_date ];
            }

            elseif (
                $this->end_date && ($this->end_date < new DateTime())
                && ! (\Auth::user() && \Auth::user()->can('buyAfterEndDate', $this))
            ) {
                //$this->availableError = [ 'Verlopen sinds %s', $this->end_date ];
                $this->availableError = [ 'Te laat' ];
            }

            else {
                $this->availableError = false;
            }
        }

        return $this->availableError;
    }

    /**
     * @return array
     */
    public function getAvailabilityWarnings()
    {
        $warnings = [];

        if ($this->max_tickets) {
            $warnings[] = [ 'Nog %s beschikbaar.', $this->countAvailableTickets() ];
        }

        if ($this->end_date) {
            $warnings[] = [ 'Registreer vóór %s.', $this->end_date ];
        }

        return $warnings;
    }

    /**
     * @param $error
     * @return string
     */
    public function errorToString($error)
    {
        if ($error && count($error) > 1) {
            $arguments = [];
            for ($i = 1; $i < count($error); $i++) {
                $att = $error[$i];
                if ($att instanceof DateTime) {
                    $arguments[] = $att->format('d/m/Y H:i');
                } else {
                    $arguments[] = $att;
                }
            }
            return vsprintf($error[0], $arguments);
        } else {
            return implode(', ', $error);
        }
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        $out = [
            '@type' => 'Offer',
            'description' => $this->name,
            'url' => action('EventController@register', [ $this->event->id, $this->id ]),
            'price' => $this->getTotalPrice(),
            'priceCurrency' => 'EUR',
            'availability' => 'http://schema.org/SoldOut'
        ];

        if ($this->countAvailableTickets() > 0) {
            $out['availability'] = 'http://schema.org/InStock';
        }

        if ($this->start_date) {
            $out['validFrom'] = $this->start_date->format('c');
        } else {
            $out['validFrom'] = $this->created_at->format('c');
        }

        if ($this->end_date) {
            $out['validThrough'] = $this->end_date->format('c');
        }

        return $out;
    }

    /**
     * @param string $separator
     */
    public function getDatesForDisplay($separator = ', ')
    {
        return $this
            ->eventDates
            ->pluck('startDate')
            ->map(function(DateTime $v) { return $v->format('d/m/Y H:i'); })
            ->join($separator);
    }

    /**
     * @return bool
     */
    public function hasFiniteTickets()
    {
        if ($this->max_tickets > 0) {
            return true;
        }

        foreach ($this->eventDates as $eventDate) {
            /** @var EventDate $eventDate */
            if ($eventDate->hasFiniteTickets()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array[]|mixed
     */
    public function getEuklesId()
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getEuklesAttributes()
    {
        $availableTickets = $this->countAvailableTickets();
        $soldTickets = $this->countSoldEventDateTickets();

        $eventDates = [];
        foreach ($this->eventDates as $eventDate) {
            $eventDates[] = $eventDate->getEuklesAttributes();
        }

        $out = [
            'name' => $this->name,
            'start' => $this->startDate ? $this->startDate->format('c') : null,
            'end' => $this->endDate ? $this->endDate->format('c') : null,
            'ticketsSold' => $soldTickets,
            'ticketsTotal' => $this->hasFiniteTickets() ? $availableTickets + $soldTickets : '∞',
            'ticketsAvailable' => $this->hasFiniteTickets() ? $availableTickets : '∞'
        ];

        if (count($eventDates) > 0) {
            $out['eventDates'] = $eventDates;
        }

        return $out;
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'ticketCategory';
    }
}
