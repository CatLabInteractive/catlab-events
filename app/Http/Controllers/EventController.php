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
use App\Models\Group;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use App\UitDB\Exceptions\UitPASException;
use Auth;
use CatLab\Accounts\Client\ApiClient;
use Illuminate\Http\Request;

/**
 * Class EventController
 * @package App\Http\Controllers
 */
class EventController extends Controller
{
    const SESSION_WAITING_LIST_ACCESS_TOKEN = 'waiting_list_access_token';

    /**
     * Show all upcoming events
     */
    public function index(Request $request)
    {
        /*
        $events = Event::upcoming()->orderBy('startDate')->get();
        $pastEvents = Event::finished()->orderBy('startDate', 'desc')->limit(5)->get();

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

        $nextEvent = null;
        $nextEventIndex = 0;
        while ($nextEvent === null) {
            $nextEvent = $organisation->events()->upcoming()
                ->published()
                ->orderBy('startDate')
                ->skip($nextEventIndex)
                ->first();

            if (!$nextEvent) {
                $nextEvent = false;
            } else if ($nextEvent->isSoldOut()) {
                $nextEvent = null;
                $nextEventIndex ++;
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
            ->orderBy('startDate')
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

        $events = $organisation->events()->upcoming()->published()->orderBy('startDate')->get();
        $pastEvents = $organisation->events()->finished()->published()->orderBy('startDate', 'desc')->get();

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

        $events = $organisation->events()->upcoming()->published()->orderBy('startDate')->get();
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

        $events = $organisation->events()->published()->upcoming()->orderBy('startDate')->get();
        $pastEvents = $organisation->events()->published()->finished()->orderBy('startDate', 'desc')->get();

        return view('events.publisher', [
            'events' => $events,
            'pastEvents' => $pastEvents,
            'nextEvent' => $this->getNextEvent($events),
            'countdownEvent' => $events->first(),
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

        $events = $venue->events()->published()->upcoming()->orderBy('startDate')->get();
        $previousEvents = $venue->events()->published()->finished()->orderBy('startDate', 'desc')->get();

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
     */
    public function view($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        $event->cancelTimeoutPendingOrders();

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
            if (isset($availableTickets) && $availableTickets <= 10) {
                $showAvailableTickets = true;
                break;
            }
        }

        $isAdmin = $user && $user->isAdmin();
        if ($isAdmin) {
            $attendees = $event->attendees()->get();
            $showAvailableTickets = true;
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
            'attendees' => $attendees,
            'showAvailableTickets' => $showAvailableTickets,
            'isAdmin' => $isAdmin,
            'ticketCategories' => $ticketCategories,
            'canonicalUrl' => $event->getUrl()
        ]);
    }

    /**
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View|void
     * @throws \Exception
     */
    public function selectTicketCategory(Request $request, $eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        if (!$event->canRegister()) {
            return redirect()->back();
        }

        // Look for a waiting list token
        $wt = $request->query('wt');
        if ($wt) {
            $request->session()->put(self::SESSION_WAITING_LIST_ACCESS_TOKEN, $wt);
        }

        /**
         *
         */
        if ($this->isSoldOut($event)) {
            return view(
                'events.registrationError',
                [
                    'event' => $event,
                    'error' => 'Dit evenement is uitverkocht.'
                ]
            );
        }

        // Select category
        $user = Auth::user();
        if (!$user) {
            // User is not logged in.
            return $this->registerLoginExplanation($event);
        }

        // Check if we have pending orders that aren't paid yet.
        $response = $this->getPendingOrdersResponse($event);
        if ($response) {
            return $response;
        }

        /** @var TicketCategory[] $categories */
        $categories = $event->ticketCategories;

        $availableTickets = [];
        foreach ($categories as $v) {
            if ($v->isAvailable()) {
                $availableTickets[] = $v;
            }
        }

        $showAvailableTickets = false;

        $user = Auth::user();
        $isAdmin = $user && $user->isAdmin();
        if ($isAdmin) {
            $attendees = $event->attendees()->get();
            $showAvailableTickets = true;
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

        if (count($availableTickets) === 1) {
            return redirect(action('EventController@register', [ $event->id, $availableTickets[0]->id ]));
        }

        return view('events.selectTicketCategory', [
            'event' => $event,
            'categories' => $categories,
            'showAvailableTickets' => $showAvailableTickets,
            'ticketCategories' => $ticketCategories,
            'canonicalUrl' => action('EventController@selectTicketCategory', [ $eventId ])
        ]);
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

            // check if we have an access token
            $accessToken = \Request::session()->get(self::SESSION_WAITING_LIST_ACCESS_TOKEN);
            if (!$accessToken) {
                return true;
            }

            /** @var User $validAccessToken */
            $validAccessToken =
                $event->waitingList()
                    ->wherePivot('access_token', '=', $accessToken)
                    ->first();

            if (!$validAccessToken) {
                return true;
            }

            // was this access token already used?
            foreach ($validAccessToken->groups as $group) {
                if (
                $event
                    ->orders()
                    ->accepted()
                    ->where('group_id', '=', $group->id)
                    ->first()
                ) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function authSelectTicketCategory(Request $request, $eventId)
    {
        return $this->selectTicketCategory($request, $eventId);
    }

    /**
     * @param Request $request
     * @param $eventId
     * @param $ticketCategoryId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function register(Request $request, $eventId, $ticketCategoryId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        // Check if we have pending orders that aren't paid yet.
        $response = $this->getPendingOrdersResponse($event);
        if ($response) {
            return $response;
        }

        if (!$event->canRegister()) {
            return redirect()->back();
        }

        /** @var TicketCategory $ticketCategory */
        $ticketCategory = $event->ticketCategories()->findOrFail($ticketCategoryId);

        if ($this->isSoldOut($event) || !$ticketCategory->isAvailable()) {
            return redirect(action('EventController@selectTicketCategory', [ $eventId ]));
        }

        // No team required? Then go straight to the confirmation.
        if (!$event->doesRequireTeam()) {
            return $this->confirmRegister($request, $eventId, $ticketCategoryId, null);
        }

        // Do we have a groupId?
        if ($groupId = \Request::get('groupId')) {
            return $this->confirmRegister($request, $eventId, $ticketCategoryId, $groupId);
        }

        $user = Auth::user();

        $groupAddUrl = action('GroupController@create', [ 'return' => \Request::url() . '?groupId={id}', 'event' => $event->id ]);

        // do we have at least one group?
        if ($user->groups()->count() === 0) {
            return redirect($groupAddUrl);
        }

        $groupValues = [];
        foreach ($user->groups as $group) {
            $groupValues[$group->id] = $group->name;
        }

        return view('events.register', [
            'action' => \Request::url(),
            'event' => $event,
            'groups' => $groupValues,
            'groupAddUrl' => $groupAddUrl,
            'canonicalUrl' => action('EventController@register', [ $eventId, $ticketCategoryId ])
        ]);
    }

    /**
     * Little explanation on why you need to register.
     * @param Event $event
     * @param TicketCategory $ticketCategory
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function registerLoginExplanation(Event $event)
    {
        if ($this->isSoldOut($event)) {
            return redirect(action('WaitingListController@waitingList', [ $event->id ]));
        }

        $nextLink = action('EventController@authSelectTicketCategory', [$event->id ]);

        return view(
            'events/whyregister',
            [
                'event' => $event,
                'showAvailableTickets' => false,
                'loginLink' => $nextLink
            ]
        );
    }

    /**
     * @param Request $request
     * @param $eventId
     * @param $ticketCategoryId
     * @param null $groupId
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function confirmRegister(Request $request, $eventId, $ticketCategoryId, $groupId = null)
    {
        $user = Auth::getUser();

        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        if (!$event->canRegister()) {
            return redirect()->back();
        }

        /** @var TicketCategory $ticketCategory */
        $ticketCategory = $event->ticketCategories()->findOrFail($ticketCategoryId);

        // Do we require a group?
        $group = null;
        if ($event->doesRequireTeam()) {
            if (!isset($groupId)) {
                $groupId = $request->input('group');
            }

            /** @var Group $group */
            $group = $user->groups()->findOrFail($groupId);

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
                ->each(function(Order $order) {
                    /*
                    $order->state = Order::STATE_CANCELLED;
                    $order->save();
                    */
                    // if i understand correctly, this should never happen.
                    $order->changeState(Order::STATE_CANCELLED);
                });
        }

        if ($this->isSoldOut($event) || !$ticketCategory->isAvailable()) {
            return redirect(action('EventController@selectTicketCategory', [ $eventId ]))
                ->withErrors([
                    'Dit type tickets is uitverkocht.'
                ]);
        }

        event(new PreparingOrder($user, $event, $this->getEuklesOriginWebsite(), $group));

        $input = [];

        if ($event->doesRequireTeam()) {
            $input['group'] = $group->id;
        }

        $priceCalculator = $ticketCategory->getTicketPriceCalculator();

        // Do we have uitpas id?
        $errors = [];
        $validUitpas = null;
        if ($request->query('uitpas')) {
            $uitPasService = \UitDb::getUitPasService();
            if ($uitPasService) {
                try {
                    $uitPasService->applyUitPasTariff($ticketCategory, $priceCalculator, $request->query('uitpas'));
                    $validUitpas = $request->query('uitpas');
                } catch (UitPASException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $input['uitpas'] = $validUitpas;
        $request->session()->put('uitpas_card_number', $validUitpas);

        $view = view('events.confirmRegister', [
            'showUiTPAS' => $event->organisation->uitpas && \UitDb::getUitPasService(),
            'action' => action('EventController@processRegister', [ $eventId, $ticketCategoryId ]),
            'uitpasAction' => action('EventController@confirmRegister', [ $eventId, $ticketCategoryId ]),
            'group' => $group,
            'input' => $input,
            'event' => $event,
            'ticketCategory' => $ticketCategory,
            'ticketPriceCalculator' => $priceCalculator,
            'canonicalUrl' => action('EventController@confirmRegister', [ $eventId, $ticketCategoryId ]),
            'uitpas' => $validUitpas
        ]);

        if ($errors) {
            $view->withErrors($errors);
        }

        return $view;
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

        $client = new ApiClient($user);

        // create a default order
        $order = $ticketCategory->createOrder($group);

        // Check if an uitpas card id was provided.
        $uitPas = $request->input('uitpas');
        if ($uitPas) {
            try {
                $uitPasService = \UitDb::getUitPasService();
                if ($uitPasService) {
                    $uitPasService->registerTicketSale($order, $uitPas);
                }
            } catch (UitPASException $e) {
                return redirect(
                    action('EventController@confirmRegister', [ $event->id, $ticketCategory->id, 'groupId' => $group->id, 'uitpas' => $uitPas ]))
                    ->withErrors([
                            $e->getMessage()
                        ]
                    );
            }
        }

        try {
            $order->save();
        } catch (\Exception $e) {
            $uitPasService = \UitDb::getUitPasService();
            if ($uitPasService) {
                $uitPasService->registerOrderCancel($order);
            }
        }

        $ticketPriceCalculator = $order->getTicketPriceCalculator();

        try {
            $orderData = $client->createOrder([

                'callback' => action('OrderController@sync', [ $order->id ]),
                'partner' => $event->organisation->catlab_partner_id,
                'items' => [
                    [
                        'name' => $event->name,
                        'description' => $group ? 'Inschrijving ' . $group->name : 'Inschrijving',
                        'amount' => 1,
                        'price' => $ticketPriceCalculator->getTicketPrice(false),
                        'vat' => $ticketPriceCalculator->getTicketPriceVat()
                    ],

                    [
                        'name' => 'Transactiekosten',
                        'amount' => 1,
                        'price' => $ticketPriceCalculator->calculateTransactionFee(),
                        'vat' => $ticketPriceCalculator->calculateTransactionFeeVat()
                    ]
                ],
                'maxTransactionFee' => $ticketPriceCalculator->calculateTransactionFee(false)

            ]);

            $order->catlab_order_id = $orderData['id'];
            $order->pay_url = $orderData['payUrl'];

            $order->save();

        } catch (\Exception $e) {
            $order->delete();
            throw $e;
        }

        // Redirect to the payment gateway.
        return redirect($order->getPayUrl());
    }

    public function scores($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $scores = $event->scores()->with('group')->orderBy('position', 'asc')->get();
        if (count($scores) === 0) {
            abort(404, 'No scores found');
        }

        return view('events.scores', [
            'event' => $event,
            'scores' => $scores
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

        $pendingOrders = $user->orders()->pending()->get();
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
