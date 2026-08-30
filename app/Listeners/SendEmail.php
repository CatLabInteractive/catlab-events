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

        // Sent with the product's client credentials (laravel-catlab-accounts
        // >= 4.1, accounts issue #99), so a member whose accounts token has
        // long expired still gets the mail.
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
     * A ticket came free and an admin invited someone off the waiting list.
     *
     * Sent as the recipient (like sendConfirmationEmail) rather than as the
     * admin: accounts rate-limits mail per user, so a mass invite spreads
     * across each invitee's own quota instead of burning the admin's.
     *
     * @param Event $event
     * @param User $user
     * @param string $url
     * @return bool whether the mail actually went out
     */
    public function sendWaitingListInvitationEmail(Event $event, User $user, string $url)
    {
        if (empty($user->email)) {
            return false;
        }

        $view = \View::make('emails.tickets.waitingListInvitation', [
            'event' => $event,
            'user' => $user,
            'url' => $url
        ]);

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);

        try {
            $apiClient->sendEmail(
                'Wachtlijst ' . $event->name,
                $view->render(),
                $user->email
            );
        } catch (GuzzleException $e) {
            \Log::warning('Waiting list invitation mail could not be sent', [
                'event' => $event->id,
                'user' => $user->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }

        return true;
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
