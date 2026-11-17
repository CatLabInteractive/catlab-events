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

namespace App\Services;

use App\Http\Controllers\EventController;
use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use App\Models\TicketCategory;
use App\Models\User;
use App\UitDB\Exceptions\UitPASException;

/**
 * Class OrderCheckout
 *
 * Turns a validated registration into an order: free tickets are accepted
 * right away, paid ones get an order at accounts and the buyer is sent to its
 * pay URL. Shared by the logged-in flow and the guest checkout.
 *
 * The buyer is passed explicitly: a guest checkout's buyer is NOT the
 * logged-in user (nobody is logged in), and must never become one here.
 *
 * @package App\Services
 */
class OrderCheckout
{
    /**
     * @param User $buyer the accounts user the order belongs to
     * @param Event $event
     * @param TicketCategory $ticketCategory
     * @param Group|null $group required on team events
     * @param string|null $uitpas a UiTPAS card number already validated for this category
     * @param bool $guest true when the buyer is not logged in (guest checkout)
     * @return Order the saved order; free orders are already accepted
     * @throws UitPASException when the UiTPAS sale could not be registered
     * @throws \Throwable when accounts could not create the order
     */
    public function checkout(
        User $buyer,
        Event $event,
        TicketCategory $ticketCategory,
        ?Group $group = null,
        ?string $uitpas = null,
        bool $guest = false
    ): Order {
        $waitingListAccessToken = EventController::getValidWaitingListToken($event);
        $order = $ticketCategory->createOrder($group, $waitingListAccessToken ?: null, $buyer);
        $order->is_guest_checkout = $guest;

        if ($ticketCategory->isFree()) {
            $order->save();
            $order->changeState(Order::STATE_ACCEPTED);
            return $order;
        }

        // Register the UiTPAS sale (if any) before the order goes to accounts.
        if ($uitpas) {
            $uitPasService = \UitDb::getUitPasService();
            if ($uitPasService) {
                $uitPasService->registerTicketSale($order, $uitpas);
            }
        }

        try {
            $order->save();
        } catch (\Exception $e) {
            $uitPasService = \UitDb::getUitPasService();
            if ($uitPasService) {
                $uitPasService->registerOrderCancel($order);
            }
            throw $e;
        }

        $ticketPriceCalculator = $order->getTicketPriceCalculator();
        $transactionFee = $ticketPriceCalculator->calculateTransactionFee();

        $items = [
            [
                'name' => $event->name,
                'description' => $group ? 'Inschrijving ' . $group->name : 'Inschrijving',
                'amount' => 1,
                'price' => $ticketPriceCalculator->getTicketPrice(false),
                'vat' => $ticketPriceCalculator->getTicketPriceVat()
            ],
        ];

        if ($transactionFee > 0) {
            $items[] = [
                'name' => 'Transactiekosten',
                'amount' => 1,
                'price' => $ticketPriceCalculator->calculateTransactionFee(),
                'vat' => $ticketPriceCalculator->calculateTransactionFeeVat()
            ];
        }

        $data = [
            // Signed per order: orders/{id}/sync has no session (audit 2026-08-27).
            'callback' => action('OrderController@sync', [ $order->id, 'sig' => $order->syncSignature() ]),
            'partner' => $event->organisation->catlab_partner_id,
            'items' => $items,
            'maxTransactionFee' => $ticketPriceCalculator->calculateTransactionFee(false)
        ];

        // Accounts hides the profile's billing details and the invoice
        // option on a guest order's pay page: whoever holds the pay URL is
        // not necessarily the owner of the account it was filed under.
        if ($guest) {
            $data['guest'] = true;
        }

        $client = app(CatLabApiClientFactory::class)->forUser($buyer);

        try {
            $orderData = $client->createOrder($data);

            $order->catlab_order_id = $orderData['id'];
            $order->pay_url = $orderData['payUrl'];

            // Second factor for refunding this order later. Accounts returns
            // it once, here: no GET endpoint hands it back.
            if (isset($orderData['refundToken'])) {
                $order->refund_token = $orderData['refundToken'];
            } else {
                \Log::warning('Paid order created without a refund token; it will not be refundable from the admin panel', [
                    'order' => $order->id,
                    'catlabOrder' => $orderData['id']
                ]);
            }

            $order->save();

        } catch (\Throwable $e) {
            $order->delete();
            throw $e;
        }

        return $order;
    }
}
