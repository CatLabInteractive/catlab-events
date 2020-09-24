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

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use App\UitDB\Exceptions\UitPASException;
use Auth;
use CatLab\Accounts\Client\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;

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

        return view('events/publisher', [
            'events' => $events,
            'pastEvents' => $pastEvents,
            'nextEvent' => $this->getNextEvent($events),
            'countdownEvent' => $events->first(),
            'cities' => $this->getCities($events)
        ]);
        */

        $organisation = $this->getOrganisation();

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

        return view('events/archive', [
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
        return view('events/registerIndex', [
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

        return view('events/publisher', [
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

        return view('events/venue', [
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

        return view('events/view', [
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

        return view('events/selectTicketCategory', [
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

        return view('events/register', [
            'showUiTPAS' => $event->organisation->uitpas && \UitDb::getUitPasService(),
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

        if (!isset($groupId)) {
            $groupId = $request->input('group');
        }

        /** @var Group $group */
        $group = $user->groups()->findOrFail($groupId);

        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        if (!$event->canRegister()) {
            return redirect()->back();
        }

        /** @var TicketCategory $ticketCategory */
        $ticketCategory = $event->ticketCategories()->findOrFail($ticketCategoryId);

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

        if ($this->isSoldOut($event) || !$ticketCategory->isAvailable()) {
            return redirect(action('EventController@selectTicketCategory', [ $eventId ]))
                ->withErrors([
                    'Dit type tickets is uitverkocht.'
                ]);
        }

        $euklesEvent = \Eukles::createEvent(
            'event.order.initialize',
            [
                'actor' => $user,
                'group' => $group,
                'event' => $event,
                'session' => $this->getEuklesOriginWebsite()
            ]
        )->link($user, 'registering', $event);

        foreach ($group->members as $member) {
            if ($member->user) {
                $euklesEvent->setObject('member', $member->user);
            }
        }

        // Track on ze eukles.
        \Eukles::trackEvent($euklesEvent);

        $input = [];
        $input['group'] = $group->id;

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

        $view = view('events/confirmRegister', [
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

        /** @var Group $group */
        $group = $user->groups()->findOrFail($request->input('group'));

        /** @var Event $event */
        $event = Event::findOrFail($eventId);

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
                $order->changeState(Order::STATE_CANCELLED);
            });

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
                'items' => [
                    [
                        'name' => $event->name,
                        'description' => 'Inschrijving ' . $group->name,
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

    /**
     * @param $eventId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportMembers($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $out = [];
        foreach ($event->attendees()->get() as $group) {
            /** @var Group $v */

            foreach ($group->members as $member) {
                /** @var GroupMember $member */
                if ($member->user) {
                    $out[] = [
                        $member->user->email,
                        $group->name,
                        $member->getName()
                    ];
                }
            }
        }

        return $this->outputCsv(
            str_slug($event->name) . '-members',
            ['email', 'team', 'username'],
            $out
        );
    }

    /**
     * @param $eventId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportSales($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $out) = $this->prepareExportSales($event);

        return $this->outputCsv(
            str_slug($event->name) . '-payments',
            $columns,
            $out
        );
    }

    public function exportSalesTimeline($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $out) = $this->prepareExportSalesOverTime($event);

        return $this->outputCsv(
            str_slug($event->name) . '-salesOverTime',
            $columns,
            $out
        );
    }

    public function exportClearing($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $teams, $sumTotal, $columnsSum) = $this->prepareExportSales($event, true);

        $organisation = $event->organisation;

        $data = [
            'event' => $event,
            'columns' => $columns,
            'rows' => $teams,
            'totals' => $columnsSum,
            'total' => $sumTotal,
            'organisation' => $organisation
        ];

        if (\Request::get('pdf') === '0') {
            return view('pdf.clearing', $data);
        }

        $pdf = PDF
            ::loadView('pdf.clearing', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download('catlab-clearing-' . $event->id . '-' . Str::slug($event->name) . '.pdf');
    }


    public function scores($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $scores = $event->scores()->with('group')->orderBy('position', 'asc')->get();
        if (count($scores) === 0) {
            abort(404, 'No scores found');
        }

        return view('events/scores', [
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
            return view('events/pendingOrder', [
                'event' => $event,
                'cancelUrl' => \Request::url() . '?cancelPendingOrders=1',
                'pendingOrder' => $pendingOrder
            ]);
        }

        return;
    }

    /**
     * @param Event $event
     * @param bool $toMoney
     * @return array
     */
    protected function prepareExportSales(Event $event, $toMoney = false)
    {
        $orders = $event->orders()->accepted()->get();

        $columns = [
            'Id',
            'Reference',
            'Date',
            'Groupname',
            'Total paid'
        ];

        $sumTotal = 0;
        $columnsSum = [];
        $columnsSum['Total paid'] = 0;

        $columnData = [];
        foreach ($orders as $order) {
            /** @var Order $order */

            $data = $order->getOrderData(true);
            $total = $data['price'] + $data['vat'];

            $tmp = [
                'Id' => $order->id,
                'Reference' => $data['reference'],
                'Date' => $order->created_at->format('Y-m-d H:i:s'),
                'Groupname' => $order->group->name,
                'Total paid' => $toMoney ? toMoney($total) : $total
            ];

            $index = 0;
            $columnNames = [ 'Ticket', 'Costs' ];

            // items and pricing
            foreach ($data['items'] as $item) {

                if (!isset($columnNames[$index])) {
                    break;
                }

                $column = $columnNames[$index];
                $vatColumn = $column . ' VAT';

                if (!in_array($column, $columns)) {
                    $columns[] = $column;
                    $columnsSum[$column] = 0;
                }

                if (!in_array($vatColumn, $columns)) {
                    $columns[] = $vatColumn;
                    $columnsSum[$vatColumn] = 0;
                }

                $price = $item['amount'] * $item['price'];
                $vat = $item['amount'] * $item['vat'];

                $tmp[$column] = $toMoney ? toMoney($price) : $price;
                $columnsSum[$column] += $price;

                $tmp[$vatColumn] = $toMoney ? toMoney($vat) : $vat;
                $columnsSum[$vatColumn] += $vat;

                $index ++;
            }

            $columnData[] = $tmp;

            $sumTotal += $total;
            $columnsSum['Total paid'] += $total;
        }

        $out = [];
        foreach ($columnData as $v) {
            $r = [];
            foreach ($columns as $c) {
                $r[$c] = isset($v[$c]) ? $v[$c] : null;
            }
            $out[] = $r;
        }

        if ($toMoney) {
            foreach ($columnsSum as $k => $v) {
                $columnsSum[$k] = toMoney($v);
            }
        }

        return [
            $columns,
            $out,
            $sumTotal,
            $columnsSum
        ];
    }

    /**
     * @param Event $event
     * @return array
     * @throws \Exception
     */
    protected function prepareExportSalesOverTime(Event $event)
    {
        $orders = $event
            ->orders()
            ->accepted()
            ->getQuery()
            ->select([
                \DB::raw('DATE(created_at) AS date'),
                \DB::raw('COUNT(id) AS sales')
            ])
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy(\DB::raw('DATE(created_at)'))
            ->get()
        ;
        $columns = [
            'Date',
            'Sales',
            'Sum'
        ];

        if (count($orders) === 0) {
            return [];
        }

        $date = new \DateTime($orders->first()->date);
        $orders = $orders->keyBy('date');

        $out = [];
        $sum = 0;

        while ($date < $event->startDate) {

            $sales = $orders->get($date->format('Y-m-d'));
            if ($sales !== null) {
                $sales = $sales->sales;
            } else {
                $sales = 0;
            }

            $sum += $sales;
            $out[] = [
                $date->format('Y-m-d'),
                $sales,
                $sum
            ];

            $date->add(new \DateInterval('P1D'));
        }

        return [
            $columns,
            $out
        ];
    }

    /**
     * @param $name
     * @param $columns
     * @param $data
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function outputCsv($name, $columns, $data)
    {
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $name . '.csv',
            'Expires'             => '0',
            'Pragma'              => 'public'
        ];

        return \Response::stream(
            function() use ($columns, $data) {
                $out = fopen('php://output', 'w');
                //fputcsv($out, $columns);

                foreach($data as $line)
                {
                    fputcsv($out, $line);
                }
                fclose($out);
            },
            200,
            $headers
        );
    }
}
