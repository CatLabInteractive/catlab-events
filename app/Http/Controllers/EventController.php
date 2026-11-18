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

namespace App\Http\Controllers;

use App\Events\PreparingOrder;
use App\Models\Event;
use App\Models\EventDate;
use App\Models\Group;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use App\UitDB\Exceptions\UitPASException;
use Auth;
use Illuminate\Http\Request;

/**
 * Class EventController
 * @package App\Http\Controllers
 */
class EventController extends Controller
{
    const SESSION_WAITING_LIST_ACCESS_TOKEN = 'waiting_list_access_token';

    /**
     * Unpaid guest-checkout orders one address may have open for one event.
     */
    const MAX_PENDING_GUEST_ORDERS = 2;

    /**
     * @return bool
     */
    public static function getValidWaitingListToken(Event $event)
    {
        // check if we have an access token
        $accessToken = \Request::session()->get(self::SESSION_WAITING_LIST_ACCESS_TOKEN);
        if (!$accessToken) {
            return false;
        }

        /** @var User $validAccessToken */
        $user = $event->waitingList()
            ->wherePivot('access_token', '=', $accessToken)
            ->first();

        if (!$user) {
            return false;
        }

        // Find any order that might be using this token
        $existingOrders = Order::accepted()
            ->waitingListAccessToken($accessToken)
            ->count();

        if ($existingOrders > 0) {
            return false;
        }

        return $accessToken;
    }

    /**
     * Show all upcoming events
     */
    public function index(Request $request)
    {
        /*
        $events = Event::upcoming()->orderByStartDate()->get();
        $pastEvents = Event::finished()->orderByStartDateDesc()->limit(5)->get();

        return view('events.publisher', [
            'events' => $events,
            'pastEvents' => $pastEvents,
            'nextEvent' => $this->getNextEvent($events),
            'countdownEvent' => $events->first(),
            'cities' => $this->getCities($events)
        ]);
        */

        $organisation = $this->getOrganisation();
        if (!$organisation) {
            return redirect('admin');
        }

        // Do we have an event that happens in the next 48 hours?
        $upcomingEvents = $organisation
            ->events()
            ->upcoming()
            ->published()
            ->orderByStartDate()
            ->limit(1);

        $upcomingEvent = $upcomingEvents->first();

        if (
            $upcomingEvent &&
            $upcomingEvent->startDate &&
            $upcomingEvent->startDate->getTimestamp() < (new \DateTime())->add(new \DateInterval('P2D'))->getTimestamp()
        ) {
            $nextEvent = $upcomingEvent;
        } else {

            $nextEvent = null;
            $nextEventIndex = 0;
            while ($nextEvent === null) {
                $nextEvent = $organisation
                    ->events()
                    ->upcoming()
                    ->published()
                    ->orderByStartDate()
                    ->skip($nextEventIndex)
                    ->first();

                if (!$nextEvent) {
                    $nextEvent = false;
                } else if ($nextEvent->isSoldOut()) {
                    $nextEvent = null;
                    $nextEventIndex++;
                }
            }

        }

        if ($nextEvent) {
            $series = $nextEvent->series;
            if ($series) {
                return app(SeriesController::class)->view($request, $series->id);
            }
        }

        // take the next upcoming event, even if it is sold out.
        $nextEvent = $organisation->events()->upcoming()
            ->published()
            ->orderByStartDate()
            ->first();

        if ($nextEvent && $nextEvent->series) {
            return app(SeriesController::class)->view($request, $nextEvent->series->id);
        }

        $series = $organisation->series()->where('active', '=', 1)->first();
        if ($series) {
            return app(SeriesController::class)->view($request, $series->id);
        } else {
            return $this->calendar();
        }
    }

    /**
     * @param $url
     * @return mixed
     */
    protected function redirect($url)
    {
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        return redirect($url);
    }

    /**
     *
     */
    public function archive()
    {
        return redirect(action('EventController@calendar'));
    }

    /**
     *
     */
    public function calendar()
    {
        $organisation = $this->getOrganisation();

        $events = $organisation->events()->upcoming()->published()->orderByStartDate()->get();
        $pastEvents = $organisation->events()->finished()->published()->orderByStartDateDesc()->get();

        return view('events.archive', [
            'events' => $events,
            'pastEvents' => $pastEvents,
            'nextEvent' => $this->getNextEvent($events),
            'countdownEvent' => $events->first(),
            'cities' => $this->getCities($events),
            'canonicalUrl' => action('EventController@calendar')
        ]);
    }

    /**
     * Show all upcoming events
     */
    public function registerIndex()
    {
        $organisation = $this->getOrganisation();

        $events = $organisation->events()->upcoming()->published()->orderByStartDate()->get();
        return view('events.registerIndex', [
            'events' => $events,
            'nextEvent' => $this->getNextEvent($events),
            'countdownEvent' => $events->first(),
            'cities' => $this->getCities($events),
            'canonicalUrl' => action('EventController@registerIndex')
        ]);
    }

    public function fromPublisher($organisationId)
    {
        /** @var Organisation $organisation */
        $organisation = Organisation::findOrFail($organisationId);

        $events = $organisation->events()->published()->upcoming()->orderByStartDate()->get();
        $pastEvents = $organisation->events()->published()->finished()->orderByStartDateDesc()->get();

        return view('events.archive', [
            'pageTitle' => $organisation->name,
            'events' => $events,
            'pastEvents' => $pastEvents,
            'nextEvent' => $this->getNextEvent($events),
            'cities' => $this->getCities($events),
            'canonicalUrl' => action('EventController@fromPublisher', [ $organisationId ])
        ]);
    }

    /**
     * @param $events
     * @return mixed
     */
    protected function getNextEvent($events)
    {
        return $events->filter(
            function(Event $event) {
                return !$event->isSoldOut();
            }
        )->first();
    }

    public function fromVenue($venueId)
    {
        /** @var Venue $venue */
        $venue = Venue::findOrFail($venueId);

        $events = $venue->events()->published()->upcoming()->orderByStartDate()->get();
        $previousEvents = $venue->events()->published()->finished()->orderByStartDateDesc()->get();

        return view('events.venue', [
            'venue' => $venue,
            'events' => $events,
            'pastEvents' => $previousEvents,
            'previousEvents' => $previousEvents,
            'nextEvent' => $this->getNextEvent($events),
            'cities' => $this->getCities($events),
            'canonicalUrl' => action('EventController@fromVenue', [ $venueId ])
        ]);
    }

    /**
     * @param $events
     * @return array
     */
    protected function getCities($events)
    {
        $cities = [];
        foreach ($events as $event) {
            if (!$event->venue) {
                continue;
            }

            $city = $event->venue->city;
            if (!isset($cities[strtolower($city)])) {
                $cities[strtolower($city)] = $event->venue->city;
            }
        }

        return $cities;
    }

    /**
     * View a single event
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function view($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        // Unpaid orders are expired by orders:expire-pending, which cancels
        // them at accounts first (a local-only cancel left the pay URL working).

        if (!$event->is_published) {
            abort(404, 'Event not found');
        }

        $user = Auth::user();

        $attendees = null;
        $showAvailableTickets = false;

        // show available tickets if it's less than 10
        foreach ($event->ticketCategories as $ticketCategory) {
            /** @var TicketCategory $ticketCategory */
            $availableTickets = $ticketCategory->countAvailableTickets();
            if (isset($availableTickets) && $availableTickets <= 10 && !$this->isSoldOut($event)) {
                $showAvailableTickets = true;
                break;
            }
        }

        $isAdmin = $user && $user->isAdmin();
        $eventDateAttendees = false;
        if ($isAdmin) {
            $showAvailableTickets = true;
            $eventDateAttendees = $this->buildEventDateAttendees($event);
        }

        $ticketCategories = $event->ticketCategories->sort(function(TicketCategory $a, TicketCategory $b) {

            if (!$b->isAvailable()) {
                return -1;
            }

            if (!$a->isAvailable()) {
                return 1;
            }

            return $a->price - $b->price;
        });

        return view('events.view', [
            'event' => $event,
            'nextEvent' => $event,
            'showAvailableTickets' => $showAvailableTickets,
            'isAdmin' => $isAdmin,
            'ticketCategories' => $ticketCategories,
            'canonicalUrl' => $event->getUrl(),
            'eventDateAttendees' => $eventDateAttendees
        ]);
    }

    /**
     * @param Event $event
     * @return array|false
     */
    protected function buildEventDateAttendees(Event $event)
    {
        if (count($event->eventDates) === 0) {
            return false;
        }

        $out = [
            'eventDates' => [],
            'maxGroups' => 0
        ];

        foreach ($event->eventDates->sortBy('startDate') as $eventDate) {
            /** @var EventDate $eventDate */
            $attendees = $eventDate->attendees()->get();
            $out['maxGroups'] = max($out['maxGroups'], $attendees->count());
            $out['eventDates'][] = [
                'date' => $eventDate->startDate,
                'groups' => $attendees
            ];
        }

        return $out;
    }

    /**
     * The one-page register form (guest checkout spec 2026-11-17). Public:
     * a guest fills in category, team name and email address and goes
     * straight to the pay page; a logged-in user gets the same page with
     * their teams and address.
     *
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Exception
     */
    public function selectTicketCategory(Request $request, $eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        if (!$event->canRegister()) {
            return redirect(action('EventController@view', [ $event->id ]));
        }

        // Look for a waiting list token
        $wt = $request->query('wt');
        if ($wt) {
            $request->session()->put(self::SESSION_WAITING_LIST_ACCESS_TOKEN, $wt);
        }

        if ($this->isSoldOut($event)) {
            return view(
                'events.registrationError',
                [
                    'event' => $event,
                    'error' => 'Dit evenement is uitverkocht.',
                    'showWaitingList' => true,
                ]
            );
        }

        $user = Auth::user();

        // Unpaid orders of this user first (logged-in users only: a guest
        // has no session to tie earlier orders to).
        if ($user) {
            $response = $this->getPendingOrdersResponse($event);
            if ($response) {
                return $response;
            }
        }

        $ticketCategories = $event->ticketCategories->sort(function(TicketCategory $a, TicketCategory $b) {
            if (!$b->isAvailable()) {
                return -1;
            }

            if (!$a->isAvailable()) {
                return 1;
            }

            return $a->price - $b->price;
        });

        $available = $ticketCategories->filter(function (TicketCategory $category) {
            return $category->isAvailable();
        });

        $selectedCategoryId = old('category', $request->query('category'));
        if (!$selectedCategoryId && $available->count() > 0) {
            $selectedCategoryId = $available->first()->id;
        }

        $groups = [];
        if ($user && $event->doesRequireTeam()) {
            foreach ($user->groups as $group) {
                $groups[$group->id] = $group->name;
            }
        }

        return view('events.registerOnePage', [
            'event' => $event,
            'user' => $user,
            'ticketCategories' => $ticketCategories,
            'availableCategories' => $available,
            'selectedCategoryId' => $selectedCategoryId ? intval($selectedCategoryId) : null,
            'groups' => $groups,
            'team' => old('team', $request->query('team')),
            'showUiTPAS' => $event->hasUitPas(),
            'recaptchaSiteKey' => $user ? null : app(\App\Services\Recaptcha::class)->getSiteKey(),
            'guestCheckout' => !$user && app(\App\Services\Recaptcha::class)->allowsGuestCheckout(),
            'loginUrl' => action('EventController@authSelectTicketCategory', [ $event->id ]),
            'canonicalUrl' => action('EventController@selectTicketCategory', [ $eventId ])
        ]);
    }

    /**
     * Submit of the one-page register form: guest checkout or logged-in.
     *
     * A guest's order is filed under the accounts user that owns the typed
     * address (found or created), WITHOUT logging anyone in. A guest's team
     * gets no members until its buyer logs in and claims it.
     *
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Exception
     */
    public function storeRegistration(Request $request, $eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        if (!$event->canRegister()) {
            return redirect(action('EventController@view', [ $event->id ]));
        }

        $back = redirect(action('EventController@selectTicketCategory', [ $event->id ]))->withInput(
            $request->except([ 'g-recaptcha-response', '_token' ])
        );

        if ($this->isSoldOut($event)) {
            return $back->withErrors([ 'Dit evenement is uitverkocht.' ]);
        }

        /** @var User|null $user */
        $user = Auth::user();
        $isGuest = !$user;

        $rules = [
            'category' => 'required|integer',
            'group' => [ 'nullable', 'regex:/^(new|\d+)$/' ],
            'uitpas' => 'nullable|string|max:64',
        ];

        // A logged-in user picks one of their teams, or "new" and types a name.
        $useExistingGroup = $user && ctype_digit((string)$request->input('group'));
        if ($event->doesRequireTeam() && !$useExistingGroup) {
            $rules['team'] = 'required|string|max:100';
        }

        if ($isGuest) {
            $rules['email'] = 'required|email|max:255';
        }

        $validator = \Validator::make($request->all(), $rules, [
            'team.required' => 'Geef een teamnaam op.',
            'email.required' => 'Geef je e-mailadres op.',
            'email.email' => 'Dat e-mailadres lijkt niet te kloppen.',
        ]);
        if ($validator->fails()) {
            return $back->withErrors($validator);
        }

        /** @var TicketCategory|null $ticketCategory */
        $ticketCategory = $event->ticketCategories()->find($request->input('category'));
        if (!$ticketCategory || !$ticketCategory->isAvailable()) {
            return $back->withErrors([ 'Dit type tickets is niet (meer) beschikbaar.' ]);
        }

        if ($isGuest && !app(\App\Services\Recaptcha::class)->allowsGuestCheckout()) {
            \Log::warning('Guest checkout refused: reCAPTCHA is not configured (set RECAPTCHA_SITE_KEY and RECAPTCHA_SECRET).');
            return $back->withErrors([ 'Log in om te bestellen.' ]);
        }

        if ($isGuest) {
            // A free order is accepted at once: as a guest that would mail an
            // unproven address, with a team name the visitor picked.
            if ($ticketCategory->isFree()) {
                return $back->withErrors([ 'Log in om je in te schrijven voor gratis tickets.' ]);
            }

            if (!app(\App\Services\Recaptcha::class)->verify($request->input('g-recaptcha-response'), $request->ip())) {
                return $back->withErrors([ 'Bevestig dat je geen robot bent.' ]);
            }
        } else {
            $response = $this->getPendingOrdersResponse($event);
            if ($response) {
                return $response;
            }
        }

        // A guest typing an active team's name: checked before anything is
        // created at accounts.
        if ($isGuest && $event->doesRequireTeam() && Group::findActiveWithName(trim((string)$request->input('team')))) {
            return $back->withErrors([ self::teamNameTakenForGuest(trim((string)$request->input('team'))) ]);
        }

        // The buyer.
        if ($isGuest) {
            try {
                $buyer = app(\App\Services\GuestBuyer::class)->resolve(trim($request->input('email')));
            } catch (\App\Services\GuestBuyerNeedsLogin $e) {
                return $back->withErrors([ 'Log in om met dit e-mailadres te bestellen.' ]);
            } catch (\Throwable $e) {
                \Log::error('Guest checkout: could not find or create the buyer at accounts', [
                    'event' => $event->id,
                    'error' => $e->getMessage()
                ]);
                return $back->withErrors([ 'Er ging iets mis. Probeer het binnen enkele minuten opnieuw.' ]);
            }

            // Typing someone else's address must not cancel their unpaid
            // orders, so a guest's earlier ones stay; cap them instead.
            $pendingGuestOrders = $buyer->orders()
                ->where('event_id', '=', $event->id)
                ->where('state', '=', Order::STATE_PENDING)
                ->where('is_guest_checkout', '=', true)
                ->count();
            if ($pendingGuestOrders >= self::MAX_PENDING_GUEST_ORDERS) {
                return $back->withErrors([
                    'Er staan al bestellingen op betaling te wachten voor dit adres. Rond die af, of probeer het over een half uur opnieuw.'
                ]);
            }
        } else {
            $buyer = $user;
        }

        // The team.
        $group = null;
        $createdGroup = null;
        if ($event->doesRequireTeam()) {
            if ($useExistingGroup) {
                /** @var Group $group */
                $group = $user->groups()->find($request->input('group'));
                if (!$group) {
                    return $back->withErrors([ 'Dat team bestaat niet.' ]);
                }

                if ($event->isRegistered($group)) {
                    return $back->withErrors([ 'Dit team is al ingeschreven.' ]);
                }

                // Cancel the team's earlier unpaid orders.
                $group
                    ->orders()
                    ->where('state', '=', Order::STATE_PENDING)
                    ->each(function (Order $order) {
                        $order->changeState(Order::STATE_CANCELLED);
                    });
            } else {
                $teamName = trim($request->input('team'));

                // Same uniqueness rule as the team form (GroupValidator).
                $holder = Group::findActiveWithName($teamName);
                if ($holder) {
                    if ($isGuest) {
                        return $back->withErrors([ self::teamNameTakenForGuest($teamName) ]);
                    }

                    // The user's own team: register that one.
                    $own = $user->groups()->find($holder->id);
                    if (!$own) {
                        return $back->withErrors([ 'Woops, te laat. Je teamnaam is niet uniek, kies alsjeblieft een andere teamnaam.' ]);
                    }
                    if ($event->isRegistered($own)) {
                        return $back->withErrors([ 'Dit team is al ingeschreven.' ]);
                    }
                    $own
                        ->orders()
                        ->where('state', '=', Order::STATE_PENDING)
                        ->each(function (Order $order) {
                            $order->changeState(Order::STATE_CANCELLED);
                        });
                    $group = $own;
                }
            }

            if ($event->doesRequireTeam() && !$group) {
                $group = new Group();
                $group->name = $teamName;
                $group->save();
                $createdGroup = $group;

                if (!$isGuest) {
                    $group->addAdmin($user);
                }
            }
        }

        // UiTPAS: validated by applying the tariff, registered on checkout.
        $uitpas = null;
        if ($event->hasUitPas() && $request->input('uitpas')) {
            $uitPasService = \UitDb::getUitPasService();
            if ($uitPasService) {
                try {
                    $uitPasService->applyUitPasTariff($ticketCategory, $ticketCategory->getTicketPriceCalculator(), $request->input('uitpas'));
                    $uitpas = $request->input('uitpas');
                } catch (UitPASException $e) {
                    return $back->withErrors([ $e->getMessage() ]);
                }
            }
        }
        $request->session()->put('uitpas_card_number', $uitpas);

        // Not for guests: the typed address is unproven until payment, and
        // must not end up in tracking (or any mail flow behind it) before then.
        if (!$isGuest) {
            event(new PreparingOrder($buyer, $event, $this->getEuklesOriginWebsite(), $ticketCategory, $group));
        }

        try {
            $order = app(\App\Services\OrderCheckout::class)->checkout(
                $buyer,
                $event,
                $ticketCategory,
                $group,
                $uitpas,
                $isGuest
            );
        } catch (UitPASException $e) {
            $this->deleteUnusedGroup($createdGroup);
            return $back->withErrors([ $e->getMessage() ]);
        } catch (\Throwable $e) {
            \Log::error('Checkout: could not create the order', [
                'event' => $event->id,
                'guest' => $isGuest,
                'error' => $e->getMessage()
            ]);
            $this->deleteUnusedGroup($createdGroup);
            return $back->withErrors([ 'Er ging iets mis. Probeer het binnen enkele minuten opnieuw.' ]);
        }

        if ($order->isAccepted()) {
            return $this->redirect($order->getThanksUrl());
        }

        return redirect($order->getPayUrl());
    }

    /**
     * Most likely a returning team: their history lives on the existing
     * team, which needs a login.
     * @param string $teamName
     * @return string
     */
    protected static function teamNameTakenForGuest(string $teamName): string
    {
        return 'De teamnaam "' . $teamName . '" is al in gebruik. Is dit jouw team? Log in om het te kiezen, '
            . 'zodat jullie scores en historiek bij elkaar blijven. Anders: kies een andere teamnaam.';
    }

    /**
     * A team created for a checkout that failed: remove it again, unless
     * something already refers to it.
     * @param Group|null $group
     */
    protected function deleteUnusedGroup(?Group $group)
    {
        if (!$group) {
            return;
        }
        try {
            if ($group->orders()->count() === 0 && $group->members()->count() === 0) {
                $group->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not remove the team of a failed checkout', [ 'group' => $group->id, 'error' => $e->getMessage() ]);
        }
    }

    /**
     * If this returns false, tickets cannot be purchased.
     * @param Event $event
     * @return bool
     */
    protected function isSoldOut(Event $event)
    {
        // is sold out?
        if ($event->isSoldOut(true)) {
            if (self::getValidWaitingListToken($event)) {
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * "Log in" from the register page: the auth middleware sends the visitor
     * through the accounts login and back to the register page.
     *
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authSelectTicketCategory(Request $request, $eventId)
    {
        return redirect(action('EventController@selectTicketCategory', [ $eventId ]));
    }

    /**
     * Old per-category links (bookmarks, mails): the one-page form with the
     * category preselected.
     *
     * @param $eventId
     * @param $ticketCategoryId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register($eventId, $ticketCategoryId)
    {
        return redirect(action('EventController@selectTicketCategory', [ $eventId, 'category' => $ticketCategoryId ]));
    }

    /**
     * @param Request $request
     * @param $eventId
     * @param $ticketCategoryId
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function processRegister(Request $request, $eventId, $ticketCategoryId)
    {
        $user = Auth::getUser();

        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $group = null;
        if ($event->doesRequireTeam()) {
            /** @var Group $group */
            $group = $user->groups()->findOrFail($request->input('group'));

            // Look for existing orders
            if ($event->isRegistered($group)) {
                return redirect()->back()->withErrors([
                    'Dit team is al ingeschreven.'
                ]);
            }

            // Cancel all pending orders
            $group
                ->orders()
                ->where('state', '=', Order::STATE_PENDING)
                ->each(function (Order $order) {
                    /*
                    $order->state = Order::STATE_CANCELLED;
                    $order->save();
                    */
                    $order->changeState(Order::STATE_CANCELLED);
                });
        }

        /** @var TicketCategory $ticketCategory */
        $ticketCategory = $event->ticketCategories()->findOrFail($ticketCategoryId);
        if (!$ticketCategory->isAvailable()) {
            return redirect()->back()->withErrors([
                'Dit type tickets is uitverkocht.'
            ]);
        }

        try {
            $order = app(\App\Services\OrderCheckout::class)->checkout(
                $user,
                $event,
                $ticketCategory,
                $group,
                $request->input('uitpas') ?: null
            );
        } catch (UitPASException $e) {
            return redirect(action('EventController@selectTicketCategory', [ $event->id, 'category' => $ticketCategory->id ]))
                ->withErrors([ $e->getMessage() ]);
        }

        if ($order->isAccepted()) {
            return $this->redirect($order->getThanksUrl());
        }

        // Redirect to the payment gateway.
        return redirect($order->getPayUrl());
    }

    public function scores($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        return view('events.scores', [
            'event' => $event
        ]);
    }

    /**
     * @param Event $event
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    protected function getPendingOrdersResponse(Event $event)
    {
        // Do we have any pending orders that we should pay first?
        $user = Auth::user();

        // Guest-checkout orders filed under this address are left out: anyone
        // can type an address, and they expire on their own.
        $pendingOrders = $user->orders()->pending()->where('is_guest_checkout', '=', false)->get();
        if (\Request::query('cancelPendingOrders')) {
            foreach ($pendingOrders as $pendingOrder) {
                /** @var Order $pendingOrder */
                $pendingOrder->changeState(Order::STATE_CANCELLED);
            }
            return;
        }

        if (count($pendingOrders) > 0) {
            $pendingOrder = $pendingOrders[0];
            return view('events.pendingOrder', [
                'event' => $event,
                'cancelUrl' => \Request::url() . '?cancelPendingOrders=1',
                'pendingOrder' => $pendingOrder
            ]);
        }

        return;
    }
}
