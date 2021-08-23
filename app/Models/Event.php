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

use App\Events\SubscribedToWaitingList;
use App\Tools\StringHelper;
use Carbon\Carbon;
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Builder;

/**
 * Class Event
 * @package App\Models
 */
class Event extends Model implements EuklesModel
{
    use SoftDeletes;

    /**
     * When should we start showing the last tickets warning?
     */
    const LAST_TICKET_WARNING = 10;

    /**
     * When should orders be canceled?
     */
    const ORDER_TIMEOUT_MINUTES = 30;

    const REGISTRATION_OPEN = 'open';
    const REGISTRATION_CLOSED = 'closed';
    const REGISTRATION_FULL = 'full';

    protected $fillable = [
        'name',
        'work_title',
        'description',
        'is_published',
        'vat_percentage',
        'include_ticket_fee',
        'registration',
        'requires_team'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function getUrl()
    {
        return action('EventController@view', [ $this->id, Str::slug($this->name) ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventDates()
    {
        return $this->hasMany(EventDate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ticketCategories()
    {
        return $this->hasMany(TicketCategory::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function logo()
    {
        return $this->belongsTo(Asset::class, 'logo_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function poster()
    {
        return $this->belongsTo(Asset::class, 'poster_id');
    }

    /**
     * @return mixed
     */
    public function getStartDateAttribute()
    {
        $out = $this->eventDates()->min('startDate');
        if ($out) {
            $out = new Carbon($out);
        }
        return $out;
    }

    /**
     * @return mixed
     */
    public function getEndDateAttribute()
    {
        $out = $this->eventDates()->max('endDate');
        if ($out) {
            $out = new Carbon($out);
        }
        return $out;
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
     * Upcoming
     * @param $builder
     * @return mixed
     * @throws \Exception
     */
    public function scopeUpcoming(\Illuminate\Database\Eloquent\Builder $builder)
    {
        $builder->where(function(\Illuminate\Database\Eloquent\Builder $builder) {

            $builder->whereIn('events.id', function($query) {
                $query->select('upcoming_event_dates.event_id')
                    ->from('event_dates as upcoming_event_dates')
                    ->whereRaw('upcoming_event_dates.event_id = events.id')
                    ->where('upcoming_event_dates.endDate', '>', new \DateTime())
                    ->orWhereNull('upcoming_event_dates.endDate')
                    ->groupBy('upcoming_event_dates.event_id');
            });

            $builder->orWhereIn('events.id', function($query) {
                $query->select('no_dates_event.id')
                    ->from('events as no_dates_event')
                    ->leftJoin('event_dates', 'no_dates_event.id', '=', 'event_dates.event_id')
                    ->whereNull('event_dates.event_id');
            });

        });
    }

    /**
     * Finished
     * @param $builder
     * @return mixed
     * @throws \Exception
     */
    public function scopeFinished($builder)
    {
        $builder->select('events.*');
        $builder->leftJoin('event_dates', 'events.id', '=', 'event_dates.event_id');
        $builder->where('event_dates.endDate', '<', new \DateTime());
    }

    /**
     * @param $builder
     * @return mixed
     */
    public function scopePublished($builder)
    {
        return $builder->where('is_published', '=', true);
    }

    /**
     * @param $builder
     * @param string $direction
     */
    public function scopeOrderByStartDate($builder, $direction = 'asc')
    {
        $builder->leftJoin(\DB::raw('(SELECT event_id, MIN(startDate) as startDate FROM event_dates GROUP BY event_id) AS edo'), 'events.id', '=', 'edo.event_id');
        $builder->orderBy('edo.startDate', $direction);
    }

    /**
     * @param $builder
     */
    public function scopeOrderByStartDateDesc($builder)
    {
        $this->scopeOrderByStartDate($builder, 'desc');
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isFinished()
    {
        if (!$this->endDate) {
            return false;
        }
        return $this->endDate < (new DateTime());
    }

    /**
     * Is a group registered and has it paid?
     * @param Group $group
     * @return bool
     */
    public function isRegistered(Group $group)
    {
        return $group
            ->orders()
            ->where('state', '=', Order::STATE_ACCEPTED)
            ->where('event_id', '=', $this->id)->count() > 0;
    }

    /**
     * @param bool $includePending
     * @return int|null
     */
    public function countAvailableTickets($includePending = true)
    {
        if (count($this->eventDates) === 0) {
            return null;
        }

        $availableTickets = 0;
        foreach ($this->eventDates as $eventDate) {
            /** @var EventDate $eventDate */
            $availableTickets += $eventDate->countAvailableTickets();
        }
        return $availableTickets;
    }

    /**
     * @param bool $includePendingTickets
     * @return bool
     */
    public function isSoldOut($includePendingTickets = false)
    {
        // registration closed? Not sold out.
        if ($this->registration === self::REGISTRATION_CLOSED) {
            return false;
        }

        // No tickets at all? Not sold out.
        if (!$this->hasTickets()) {
            return false;
        }

        if ($this->registration === self::REGISTRATION_FULL) {
            return true;
        }

        if (!$includePendingTickets) {
            $availableTickets = $this->countAvailableTickets($includePendingTickets);
            if ($availableTickets === null) {
                return false;
            }

            if ($availableTickets && $availableTickets <= 0) {
                $this->registration = self::REGISTRATION_FULL;
                $this->save();
                return true;
            }

            return false;
        }

        // if pending tickets should be included, we should look at the raw method.
        $availableTickets = $this->countAvailableTickets($includePendingTickets);
        if ($availableTickets === null) {
            return false;
        }

        return $availableTickets <= 0;
    }

    /**
     * @return bool
     */
    public function isSelling()
    {
        if (!$this->hasTickets()) {
            return false;
        }

        if ($this->registration === self::REGISTRATION_CLOSED) {
            return false;
        }

        return $this->getNotSellingReason() === false;
    }

    /**
     * @return bool
     */
    public function willSell()
    {
        if (!$this->hasTickets()) {
            return false;
        }

        if ($this->registration === self::REGISTRATION_CLOSED) {
            return false;
        }

        return $this->getNotSellingReason() !== 'Te laat';
    }

    /**
     * @return bool
     */
    public function isLastTicketsWarning()
    {
        if (!$this->hasTickets()) {
            return false;
        }

        return $this->countAvailableTickets() <= self::LAST_TICKET_WARNING;
    }

    /**
     *
     */
    public function getNotSellingReason()
    {
        if ($this->isSoldOut(true)) {
            return 'Uitverkocht';
        }

        // Already selling tickets?
        $startDate = null;

        $ticketCategories = $this->ticketCategories;
        $ticketCategories =
            $ticketCategories->filter(function(TicketCategory $category) {
                return $category->isAvailable();
            })
            ->sortBy('startDate');

        if ($ticketCategories->count() === 0) {
            if (count($this->ticketCategories) > 0) {

                $nextTicketCategory = $this
                    ->ticketCategories
                    ->sortByDesc('startDate')
                    ->first();

                return $nextTicketCategory->errorToString($nextTicketCategory->getAvailableError());

            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * @return TicketCategory|null
     * @throws \Exception
     */
    protected function getRelevantTicketCategory()
    {
        $ticketCategories = $this->ticketCategories;
        $ticketCategories =
            $ticketCategories->filter(function(TicketCategory $category) {
                return $category->isAvailable();
            })
                ->sortBy('price');

        if ($ticketCategories->count() === 0) {
            if (count($this->ticketCategories) > 0) {

                $nextTicketCategory = $this
                    ->ticketCategories
                    ->sortBy('startDate')
                    ->first();

                return $nextTicketCategory;

            } else {
                return null;
            }
        }

        return $ticketCategories->first();
    }

    /**
     * @return bool
     */
    public function isRegistrationClosed()
    {
        return $this->registration === self::REGISTRATION_CLOSED;
    }

    /**
     *
     */
    public function hasSaleStarted()
    {
        if ($this->isRegistrationClosed()) {
            return false;
        }

        if ($this->isSoldOut()) {
            return true;
        }

        if ($this->getNotSellingReason() === false) {
            return true;
        }

        if (!$this->willSell()) {
            return true;
        }

        return false;
    }

    /**
     * @return DateTime
     * @throws \Exception
     */
    public function getSaleStartDate()
    {
        $nextCategory = $this->getRelevantTicketCategory();
        if (!$nextCategory) {
            return null;
        }

        return $nextCategory->start_date;
    }

    /**
     * @param bool $includePending
     * @return int
     */
    public function countSoldTickets($includePending = true)
    {
        $states = [
            Order::STATE_ACCEPTED
        ];

        if ($includePending) {
            $states[] = Order::STATE_PENDING;
        }

        return $this
            ->orders()
            ->whereIn('state', $states)
            ->count();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function canRegister()
    {
        if (!$this->hasTickets()) {
            return false;
        }

        if ($this->isRegistrationClosed()) {
            return false;
        }

        if ($this->startDate && $this->startDate > new \DateTime() && !$this->isFinished()) {
            return true;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function hasTickets()
    {
        return $this->ticketCategories->count() > 0;
    }

    /**
     * Called on view. Remove all pending tickets
     */
    public function cancelTimeoutPendingOrders()
    {
        $this->orders()
            ->where('state', '=', Order::STATE_PENDING)
            ->where('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . self::ORDER_TIMEOUT_MINUTES . 'M')))
            ->update([
                'state' => Order::STATE_CANCELLED
            ]);
    }

    /**
     * @return Collection
     */
    public function getPublishedPrices()
    {
        // Get all valid tickets
        $tickets = $this->ticketCategories->filter(function(TicketCategory $ticket) {
            return $ticket->willBecomeAvailable();
        });

        // Order by price
        $tickets = $tickets->sort(function(TicketCategory $a, TicketCategory $b) {
            return $a->price - $b->price;
        });

        return $tickets;
    }

    /**
     * @param bool $showDiscount
     * @return string
     */
    public function getFormattedPublishedPrice($showDiscount = true)
    {
        $prices = $this->getPublishedPrices();

        if ($prices->count() === 0) {
            return null;
        } else if (!$showDiscount || $prices->count() === 1) {
            return $prices->first()->getFormattedTotalPrice();
        } else {
            $cheapest = $prices->first();
            $expensivest = $prices->last();

            // Does 'cheapest' have an end date or are the prices the same?
            if (
                !isset($cheapest->end_date) ||
                $cheapest->price === $expensivest->price
            ) {
                return $cheapest->getFormattedTotalPrice();
            }

            $title = $this->getPublishedPriceDetails();

            return '<strike>' . $expensivest->getFormattedTotalPrice() . '</strike> ' .
                '<strong title="' . $title . '">' . $cheapest->getFormattedTotalPrice() . ' (' . $cheapest->name . '*)</strong>';
        }
    }

    /**
     * @return string
     */
    public function getWorkTitleOrName()
    {
        if ($this->work_title) {
            return $this->work_title;
        } else {
            return $this->name;
        }
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        if (!$this->startDate) {
            return [];
        }

        $output = [
            "@context" => "http://www.schema.org",
            "@type" => "Event",
            "name" => $this->name,
            "url" => $this->getUrl(),
            "description" => StringHelper::htmlToText($this->description),
            "startDate" => $this->startDate->format('c'),
            "endDate" => $this->endDate->format('c'),
            "images" => [],
            /*
            'performer' => [
                '@type' => 'PerformingGroup',
                'name' => $this->organisation->name
            ]
            */
            'superEvent' => $this->series ? $this->series->getJsonLD() : null
        ];

        $output['performer'] = [];
        foreach ($this->presenters as $presenter) {
            $output['performer'][] = $presenter->getJsonLD();
        }

        if ($this->venue) {
            $output['location'] = $this->venue->getJsonLD();
        } elseif ($this->getLiveStreamUrl()) {
            $output['location'] = [
                "@type" => "VirtualLocation",
                "name" => $this->getLiveStreamUrl(),
                "url" => $this->getLiveStreamUrl()
            ];
        }

        if ($this->poster) {
            $output['images'][] = $this->poster->getUrl();
        }

        if ($this->logo) {
            $output['images'][] = $this->logo->getUrl();
        }

        if (count($output['images']) === 0) {
            $output['images'][] = url('images/fotos/QuizWitz-Live.jpg');
        }

        $output['image'] = $output['images'][0];

        if (count($this->ticketCategories) > 0) {
            $output['offers'] = [];
            foreach ($this->ticketCategories as $ticketCategory) {
                /** @var TicketCategory $ticketCategory */
                $output['offers'][] = $ticketCategory->getJsonLD();
            }
        } else {
            $output['isAccessibleForFree'] = true;
        }

        // Same as
        if ($this->event_url) {
            if (!isset($output['sameAs'])) {
                $output['sameAs'] = [];
            }

            $output['sameAs'][] = $this->event_url;
        }

        if ($this->getFacebookEventUrl()) {
            $output['sameAs'][] = $this->getFacebookEventUrl();
        }

        // Organiser
        $output['organizer'] = $this->organisation->getJsonLD();

        $output['eventStatus'] = 'EventScheduled';

        // attendance
        if ($this->getLiveStreamUrl() && $this->venue) {
            $output['eventAttendanceMode'] = 'MixedEventAttendanceMode';
        } elseif ($this->venue) {
            $output['eventAttendanceMode'] = 'OfflineEventAttendanceMode';
        } elseif ($this->getLiveStreamUrl()) {
            $output['eventAttendanceMode'] = 'OnlineEventAttendanceMode';
        }

        // performed work
        $output['workPerformed'] = [
            '@type' => 'Game',
            'name' => $this->getWorkTitleOrName()
        ];

        foreach ($this->authors as $author) {
            if (!isset($output['workPerformed']['author'])) {
                $output['workPerformed']['author'] = [];
            }

            $output['workPerformed']['author'][] = $author->getJsonLD();
        }

        return $output;
    }

    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->name . ' - ' . $this->venue->city;
    }

    /**
     * Get facebook event url if set.
     * @return null|string
     */
    public function getFacebookEventUrl()
    {
        if (empty($this->fb_event_id)) {
            return null;
        }

        return 'https://www.facebook.com/events/' . $this->fb_event_id;
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

        $score->event()->associate($this);
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function waitingList()
    {
        return $this->belongsToMany(User::class, 'event_waitinglist')
            ->withTimestamps();
    }

    /**
     * @param Event $event
     * @return bool
     */
    public function equals(Event $event)
    {
        return $this->id === $event->id;
    }

    public function registerToWaitingList($user)
    {
        $this->waitingList()->save($user);

        event(new SubscribedToWaitingList($this, $user));
    }

    public function people()
    {
        return $this->belongsToMany(Person::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function authors()
    {
        return $this->people()->wherePivot('role', '=', Person::ROLE_AUTHOR);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function presenters()
    {
        return $this->people()->wherePivot('role', '=', Person::ROLE_PRESENTER);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function musicians()
    {
        return $this->people()->wherePivot('role', '=', Person::ROLE_MUSICIAN);
    }

    public function saveManyAuthors($children)
    {
        $this->saveManyPeople($children, Person::ROLE_AUTHOR);
    }

    public function saveManyPresenters($children)
    {
        $this->saveManyPeople($children, Person::ROLE_PRESENTER);
    }

    public function saveManyMusicians($children)
    {
        $this->saveManyPeople($children, Person::ROLE_MUSICIAN);
    }

    protected function saveManyPeople($children, $role)
    {
        $pivotAttributes = [];
        foreach ($children as $child) {
            $pivotAttributes[] = [
                'role' => $role
            ];
        }
        $this->people()->saveMany($children, $pivotAttributes);
    }

    /**
     * @return string
     */
    public function getUitDBId()
    {
        return $this->uitdb_event_id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function livestream()
    {
        return $this->hasOne(LiveStream::class);
    }

    /**
     * @return string|null
     */
    public function getLiveStreamUrl()
    {
        if ($this->livestream_url) {
            return $this->livestream_url;
        }

        if (!$this->livestream) {
            return null;
        }
        return $this->livestream->getLivestreamUrl();
    }

    /**
     * @param Group|null $group
     * @return string|null
     */
    public function getIdentifiedLiveStreamUrl(Group $group = null)
    {
        $url = $this->getLiveStreamUrl();
        if (!$url) {
            return null;
        }

        $additionalParameters = [];
        if ($group) {
            $additionalParameters['g'] = $group->id;
            $additionalParameters['n'] = $group->name;
        }

        if (count($additionalParameters) === 0) {
            return $url;
        }

        if (strpos($url, '?') !== false) {
            $url .= '&';
        } else {
            $url .= '?';
        }

        return $url . http_build_query($additionalParameters);
    }

    /**
     * @return bool
     */
    public function doesRequireTeam()
    {
        return $this->requires_team;
    }

    /**
     * @return bool
     */
    public function hasUitPas()
    {
        return $this->organisation->uitpas &&
            $this->uitdb_event_id &&
            \UitDb::getUitPasService();
    }

    /**
     * @return bool
     */
    public function isQuizWitzCampaign()
    {
        return $this->campaign_id;
    }

    /**
     * Return the name of the 'action' that is taken when users are subscribing/buying (in dutch)
     */
    public function getOrderLabel()
    {
        if ($this->isQuizWitzCampaign()) {
            return 'Bestellen';
        } else {
            return 'Inschrijven';
        }
    }

    /**
     * @return string|null
     */
    public function getUrgencyMessage()
    {
        if (!$this->isSelling() || $this->isSoldOut()) {
            return null;
        }

        if ($this->isLastTicketsWarning()) {
            $availableTickets = $this->countAvailableTickets();
            return "Laatste $availableTickets tickets!";
        }

        $nextTicketCategory = $this->getRelevantTicketCategory();
        if (
            $nextTicketCategory &&
            $nextTicketCategory->end_date
        ) {
            if ($nextTicketCategory->end_date->getTimestamp() < (time() + (60 * 60))) {
                return 'Laatste uur "' . $nextTicketCategory->name . '"!';
            } elseif ($nextTicketCategory->end_date->getTimestamp() < (time() + (12 * 60 * 60))) {
                return 'Laatste uren "' . $nextTicketCategory->name . '"!';
            } elseif ($nextTicketCategory->end_date->getTimestamp() < (time() + (24 * 60 * 60))) {
                return 'Laatste dag "' . $nextTicketCategory->name . '"!';
            } elseif ($nextTicketCategory->end_date->getTimestamp() < (time() + (3 * 24 * 60 * 60))) {
                return 'Laatste dagen "' . $nextTicketCategory->name . '"!';
            }
        }

        return null;
    }

    /**
     * @param bool $showDiscount
     * @return string|null
     */
    public function getPublishedPriceDetails($showDiscount = true)
    {
        $prices = $this->getPublishedPrices();

        if ($prices->count() === 0) {
            return null;
        }

        if (!$showDiscount) {
            return null;
        }

        $out = '';
        if ($prices->count() > 1) {
            $cheapest = $prices->first();

            if ($cheapest->end_date) {
                $out = 'Bestel voor ' . $cheapest->end_date->formatLocalized('%A %d %B %Y, %H:%M') . '. ';
            }
        }

        if ($this->hasUitPas()) {
            $out .= "UiTPAS kansentarief beschikbaar. ";
        }

        return $out;
    }

    /**
     * @return array[]
     */
    public function getEuklesId()
    {
        return $this->id;
    }

    /**
     * @return array[]
     */
    public function getEuklesAttributes()
    {
        $out = [
            'uid' => $this->id,
            'name' => $this->name,
            'url' => $this->getUrl(),
            'start' => $this->startDate ? $this->startDate->format('c') : null,
            'end' => $this->endDate ? $this->endDate->format('c') : null,
            'facebookEventUrl' => $this->getFacebookEventUrl()
        ];

        if ($this->venue) {
            $out = array_merge($out, [
                'venue' => $this->venue->name,
                'address' => $this->venue->address,
                'postalCode' => $this->venue->postalCode,
                'city' => $this->venue->city,
                'country' => $this->venue->country,
            ]);
        }

        return $out;
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'event';
    }
}
