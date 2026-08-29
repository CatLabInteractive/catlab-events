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

        $retryFormAction = action('EventController@processRegister', [ $order->event->id, $order->ticketCategory->id ] );
        $retryFormInput = [
            'group' => $order->group ? $order->group->id : null
        ];

        if (\Request::session()->get('uitpas_card_number')) {
            $retryFormInput['uitpas'] = \Request::session()->get('uitpas_card_number');
        }

        return view(
            'orders/thanks',
            [
                'order' => $order,
                'trackConversion' => $trackConversion,
                'redirectUrl' => $order->getThanksUrl(),
                'retryFormAction' => $retryFormAction,
                'retryFormInput' => $retryFormInput
            ]
        );
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
