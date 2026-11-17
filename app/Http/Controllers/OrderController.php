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

use App\Models\Order;

/**
 * Class OrderController
 * @package App\Http\Controllers
 */
class OrderController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $user = \Auth::getUser();

        $orders = $user
            ->orders()
            ->where('state', '!=', Order::STATE_CANCELLED)
            ->orderBy('id', 'desc')
        ;

        return view(
            'orders/index',
            [
                'orders' => $orders->get()
            ]
        );
    }

    /**
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        $order->synchronize();

        $orderData = $order->getOrderData(true);
        return view(
            'orders/view',
            [
                'order' => $order,
                'orderData' => $orderData,
                'ticketCategory' => $order->ticketCategory
            ]
        );
    }

    /**
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function thanks($orderId)
    {
        $forceTracker = \Request::get('forceTracking');
        $forceTrigger = false;

        if (\Auth::user() && \Auth::user()->isAdmin()) {
            $forceTrigger = !!\Request::get('forceTrigger');
        }

        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        // The payment return URL is often opened on another device (QR code
        // scanned to pay on a phone), so a session cannot be required: the
        // URL handed to the payer carries a per-order signature. Logged-in
        // owners / group members / admins (OrderPolicy::view) need no signature.
        abort_unless(
            \Gate::allows('view', $order) || $order->verifyThanksSignature(\Request::get('sig')),
            403
        );

        $order->synchronize($forceTrigger);

        $trackConversion = !!$forceTracker;
        if ($order->isAccepted() && !$order->tracker_sent) {
            $trackConversion = true;
            $order->tracker_sent = 1;
            $order->save();
        }

        // "Try again" goes back to the one-page form, prefilled.
        $retryQuery = [ $order->event->id, 'category' => $order->ticketCategory->id ];
        if ($order->group) {
            $retryQuery['team'] = $order->group->name;
        }
        $retryUrl = action('EventController@selectTicketCategory', $retryQuery);

        // The thanks URL is signed, not tied to a login: on a guest order,
        // only show the buyer's own address to the buyer.
        $email = $order->user ? $order->user->email : null;
        if ($email && $order->is_guest_checkout && \Auth::id() !== $order->user_id) {
            $email = self::maskEmail($email);
        }

        $event = $order->event;
        $ticketCategory = $order->ticketCategory;
        $eventDates = $ticketCategory->eventDates->sortBy('startDate')->values();

        $livestreamUrl = null;
        if ($order->isAccepted()) {
            $livestreamUrl = $event->getIdentifiedLiveStreamUrl($order->group);
        }

        return view(
            'orders/thanks',
            [
                'order' => $order,
                'event' => $event,
                'ticketCategory' => $ticketCategory,
                'eventDates' => $eventDates,
                'firstDate' => $eventDates->first(),
                'isPackage' => $event->isPackage() || $event->isQuizWitzCampaign(),
                'livestreamUrl' => $livestreamUrl,
                'email' => $email,
                'isGuestCheckout' => (bool)$order->is_guest_checkout,
                'calendarUrl' => action('OrderController@calendar', [ $order->id, 'sig' => $order->thanksSignature() ]),
                'trackConversion' => $trackConversion,
                'redirectUrl' => $order->getThanksUrl(),
                'retryUrl' => $retryUrl
            ]
        );
    }

    /**
     * "Beheer je team" from a guest checkout's confirmation mail. A guest's
     * team has no members; the order's buyer, once logged in (and so proven
     * to own the address), becomes its admin.
     *
     * @param $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function claim($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);
        abort_unless(\Auth::id() !== null && (int)\Auth::id() === (int)$order->user_id, 403);
        // Only a paid guest checkout's team is claimable (same rule as the
        // login listener): an unpaid order can be made by anyone typing the
        // address, with any team name.
        abort_unless($order->is_guest_checkout && $order->isAccepted(), 404);

        $group = $order->group;
        if ($group && !$group->hasMembers()) {
            $group->addAdmin(\Auth::user());
        }

        if ($group) {
            return redirect(action('GroupController@show', [ $group->id ]));
        }

        return redirect(action('OrderController@view', [ $order->id ]));
    }

    /**
     * t***@example.com
     *
     * @param string $email
     * @return string
     */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }
        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }

    /**
     * Download the order's dates as an .ics file (linked from the thanks page).
     * Same access rule as the thanks page: owner / group member / admin, or the
     * signed thanks URL.
     *
     * @param $orderId
     * @return \Illuminate\Http\Response
     */
    public function calendar($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        abort_unless(
            \Gate::allows('view', $order) || $order->verifyThanksSignature(\Request::get('sig')),
            403
        );
        abort_unless($order->isAccepted(), 404);

        $content = app(\App\Services\OrderCalendar::class)->render($order);
        $filename = \Illuminate\Support\Str::slug($order->event->name) ?: 'quiz';

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.ics"',
        ]);
    }

    /**
     * @param $orderId
     * @return string
     */
    public function sync($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        // Accounts' notify callback: no session possible, so the URL we
        // handed to accounts carries a per-order signature (see
        // EventController::processRegister / Order::syncSignature). Orders
        // registered before that URL was signed are grandfathered while
        // their event is upcoming (Order::acceptsUnsignedSync).
        abort_unless(
            $order->verifySyncSignature(\Request::get('sig')) || $order->acceptsUnsignedSync(),
            403
        );

        $order->synchronize();

        return \Response::json([ 'success' => 1 ]);
    }
}
