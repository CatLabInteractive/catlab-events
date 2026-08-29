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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

/**
 * Class RefundController
 *
 * Refunding a paid order through accounts. The money really moves, so the
 * admin passes a confirmation page that shows the live amount and asks
 * them to type the order reference first.
 *
 * @package App\Http\Controllers\Admin
 */
class RefundController extends Controller
{
    /**
     * Whether this order can be refunded through the API at all.
     *
     * Orders created before the refund token existed cannot: accounts
     * answers 404 without one. Those are refunded from the accounts admin
     * panel instead.
     *
     * The catlab_order_id check also covers free tickets: they never get an
     * accounts order, so there is no payment to refund and no separate
     * price check is needed.
     *
     * @param Order $order
     * @return bool
     */
    public static function isRefundable(Order $order)
    {
        return $order->state === Order::STATE_ACCEPTED
            && $order->catlab_order_id
            && $order->refund_token;
    }

    /**
     * Show the confirmation page. Sends nothing.
     *
     * The Charon table action renders on every order row, so an order that
     * cannot be refunded here gets an explanation rather than a 404 -- a
     * dead link would just look broken.
     *
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function refund($orderId)
    {
        $order = $this->getOrderInOrganisation($orderId);

        if (!self::isRefundable($order)) {
            return view('admin.orders.refund', [
                'order' => $order,
                'refundable' => false,
                'reference' => null,
                'amount' => null
            ]);
        }

        $orderData = $order->getOrderData(true);

        return view('admin.orders.refund', [
            'order' => $order,
            'refundable' => true,
            'reference' => isset($orderData['reference']) ? $orderData['reference'] : null,
            'amount' => isset($orderData['price']) ? $orderData['price'] : null
        ]);
    }

    /**
     * The order, or 404. Scoped to the acting admin's active organisation:
     * `admin` is a global flag, so without this an admin of one
     * organisation could refund another's order. This one really is a 404 --
     * the order is none of this admin's business.
     *
     * @param $orderId
     * @return Order
     */
    protected function getOrderInOrganisation($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$order->event || !$organisation || $order->event->organisation_id !== $organisation->id) {
            abort(404);
        }

        return $order;
    }
}
