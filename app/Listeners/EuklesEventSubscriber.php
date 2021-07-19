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

namespace App\Listeners;

use App\Events\DonationCancelled;
use App\Events\DonationReceived;
use App\Events\GroupMemberJoined;
use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\PreparingOrder;
use App\Events\SubscribedToWaitingList;
use Illuminate\Events\Dispatcher;

/**
 * Class EuklesEventSubscriber
 * @package App\Listeners
 */
class EuklesEventSubscriber
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * @param PreparingOrder $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onPreparingOrder(PreparingOrder $e)
    {
        $euklesProperties = [
            'actor' => $e->actor,
            'event' => $e->event,
            'session' => $e->session
        ];

        if ($e->group) {
            $euklesProperties['group'] = $e->group;
        }

        $euklesEvent = \Eukles::createEvent(
            'event.order.initialize',
            $euklesProperties
        )->link($e->actor, 'registering', $e->event);

        if ($e->group) {
            foreach ($e->group->members as $member) {
                if ($member->user) {
                    $euklesEvent->setObject('member', $member->user);
                }
            }
        }

        // Track on ze eukles.
        \Eukles::trackEvent($euklesEvent);
    }

    /**
     * @param OrderConfirmed $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onOrderConfirmed(OrderConfirmed $e)
    {
        $order = $e->order;

        $attributes = [
            'event' => $order->event,
            'order' => $order
        ];

        if ($order->group) {
            $attributes['group'] = $order->group;
        }

        // Track on ze eukles.
        $euklesEvent = \Eukles::createEvent('event.order.confirmed', $attributes);

        $euklesEvent->unlink($order->user, 'registering', $order->event);

        if ($order->group) {
            $euklesEvent->link($order->group, 'attends', $order->event);
            foreach ($order->group->members as $member) {
                if ($member->user) {
                    $euklesEvent->setObject('member', $member->user);
                }
            }
        }

        \Eukles::trackEvent($euklesEvent);
    }

    /**
     * @param OrderCancelled $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onOrderCancelled(OrderCancelled $e)
    {
        $order = $e->order;

        // Only trigger event when the order was previously 'confirmed'.
        if (!$e->wasConfirmed) {
            return;
        }

        $attributes = [
            'event' => $order->event,
            'order' => $order
        ];

        if ($order->group) {
            $attributes['group'] = $order->group;
        }

        // Track on ze eukles.
        $euklesEvent = \Eukles::createEvent('event.order.cancel', $attributes);

        if ($order->group) {
            $euklesEvent->unlink($order->group, 'attends', $order->event);

            foreach ($order->group->members as $member) {
                if ($member->user) {
                    $euklesEvent->setObject('member', $member->user);
                }
            }
        }

        \Eukles::trackEvent($euklesEvent);
    }

    /**
     * @param DonationCancelled $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onDonationCancelled(DonationCancelled $e)
    {
        // payment canceled, restock items
        \Eukles::trackEvent(
            \Eukles::createEvent(
                'donation.cancelled',
                $e->transaction
            )
        );
    }

    /**
     * @param DonationReceived $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onDonationReceived(DonationReceived $e)
    {
        // process the payment
        // Track on ze eukles.
        \Eukles::trackEvent(
            \Eukles::createEvent(
                'donation.success',
                $e->transaction
            )
        );
    }

    /**
     * @param GroupMemberJoined $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onGroupJoin(GroupMemberJoined $e)
    {
        $group = $e->group;
        $user = $e->member->user;

        // Track on ze eukles.
        \Eukles::trackEvent(
            \Eukles::createEvent(
                'group.member.join',
                [
                    'group' => $group,
                    'user' => $user
                ]
            )
                ->link($user, 'ismemberof', $group)
        );
    }

    /**
     * @param SubscribedToWaitingList $e
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onSubscribedToWaitingList(SubscribedToWaitingList $e)
    {
        // Track on ze eukles.
        \Eukles::trackEvent(
            \Eukles::createEvent(
                'event.waitinglist.subscribe',
                [
                    'user' => $e->user,
                    'event' => $e->event
                ]
            )
                ->link($e->user, 'waitinglist', $this)
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     */
    public function subscribe(Dispatcher $events)
    {
        // If eukles is not defined, don't bother registering the events.
        if (!config('eukles.key')) {
            return;
        }

        $events->listen(PreparingOrder::class, EuklesEventSubscriber::class . '@onPreparingOrder');
        $events->listen(OrderConfirmed::class, EuklesEventSubscriber::class . '@onOrderConfirmed');
        $events->listen(OrderCancelled::class, EuklesEventSubscriber::class . '@onOrderCancelled');
        $events->listen(DonationCancelled::class, EuklesEventSubscriber::class . '@onDonationCancelled');
        $events->listen(DonationReceived::class, EuklesEventSubscriber::class . '@onDonationReceived');
        $events->listen(GroupMemberJoined::class, EuklesEventSubscriber::class . '@onGroupJoin');
        $events->listen(SubscribedToWaitingList::class, EuklesEventSubscriber::class . '@onSubscribedToWaitingList');
    }
}
