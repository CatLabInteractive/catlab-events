<?php

namespace App\Listeners;

use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use App\Models\User;
use CatLab\Accounts\Client\ApiClient;

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
        $apiClient = new ApiClient($user);

        $apiClient->sendEmail(
            $event->name . ': We zijn er bij!',
            $view->render(),
            $user->email
        );
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
        $apiClient = new ApiClient($user);

        $apiClient->sendEmail(
            $order->event->name . ': We zijn er niet bij :(',
            $view->render(),
            $user->email
        );
    }
}
