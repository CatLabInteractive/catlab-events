<?php

namespace App\Listeners;

use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class SendEmail
 * @package App\Listeners
 */
abstract class SendEmail
{
    /**
     * @param Order $order
     * @param Event $event
     * @param User $user
     */
    public function sendConfirmationEmail(Order $order, Event $event, User $user)
    {
        /** @var Group $group */
        $group = $order->group;

        $attributes = [
            'order' => $order,
            'from' => \Auth::getUser(),
            'event' => $event,
            'group' => $group,
            'ticketCategory' => $order->ticketCategory
        ];

        if ($event->confirmation_email && view()->exists($event->confirmation_email)) {
            $view = \View::make($event->confirmation_email, $attributes);
        } elseif ($order->play_link) {
            $view = \View::make('emails.tickets.confirmationPlayLink', $attributes);
        } else {
            $view = \View::make('emails.tickets.confirmation', $attributes);
        }

        /** @var User $user */
        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);

        /* @TODO
         * This is not okay as some group members might not have logged in for a long time and might thus have
         * an expired access token, resulting in these emails not being send.
         */

        try {
            $apiClient->sendEmail(
                $event->name . ': We zijn er bij!',
                $view->render(),
                $user->email
            );
        } catch (GuzzleException $e) {
            \Log::error($e);
        }
    }

    /**
     * @param Order $order
     * @param User $user
     */
    public function sendCancellationEmail(Order $order, User $user)
    {
        /** @var Group $group */
        $group = $order->group;

        if (empty($user->email)) {
            return;
        }

        $attributes = [
            'from' => \Auth::getUser(),
            'event' => $order->event,
            'group' => $group
        ];

        $view = \View::make('emails/tickets/cancellation', $attributes);

        /** @var User $user */
        $user = $order->user;
        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);

        try {
            $apiClient->sendEmail(
                $order->event->name . ': We zijn er niet bij :(',
                $view->render(),
                $user->email
            );
        } catch (GuzzleException $e) {
            \Log::error($e);
        }
    }
}
