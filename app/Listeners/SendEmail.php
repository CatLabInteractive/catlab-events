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
     * @param User|null $user
     * @param string|null $email overrides the user's address; guest orders have
     *   no user and are mailed at the address the admin entered instead.
     */
    public function sendConfirmationEmail(Order $order, Event $event, ?User $user, $email = null)
    {
        $recipient = $email ?: ($user ? $user->email : null);
        if (empty($recipient)) {
            return;
        }

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

        $sender = $this->getSender($user, $order);
        if (!$sender) {
            return;
        }

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($sender);

        // Sent with the product's client credentials (laravel-catlab-accounts
        // >= 4.1, accounts issue #99), so a member whose accounts token has
        // long expired still gets the mail.
        try {
            $apiClient->sendEmail(
                $event->name . ': We zijn er bij!',
                $view->render(),
                $recipient
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
     * @param string|null $subject edited by an admin, null for the default
     * @param string|null $content edited by an admin, null for the default template
     * @return bool whether the mail actually went out
     */
    public function sendWaitingListInvitationEmail(
        Event $event,
        User $user,
        string $url,
        ?string $subject = null,
        ?string $content = null
    ) {
        if (empty($user->email)) {
            return false;
        }

        if ($content !== null) {
            $view = \View::make('emails.tickets.customContent', [
                'content' => $content
            ]);
        } else {
            $view = \View::make('emails.tickets.waitingListInvitation', [
                'event' => $event,
                'user' => $user,
                'url' => $url
            ]);
        }

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($user);

        try {
            $apiClient->sendEmail(
                $subject ?: \App\Events\InvitedFromWaitingList::defaultSubject($event),
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
     * @param User|null $user
     * @param string|null $email set for guest orders, which have no user
     */
    public function sendCancellationEmail(Order $order, ?User $user, $email = null)
    {
        /** @var Group $group */
        $group = $order->group;

        if ($email === null) {
            if (!$user || empty($user->email)) {
                return;
            }

            // Historically sent to the buyer's address, not the member's.
            $email = $order->user ? $order->user->email : $user->email;
        }

        $attributes = [
            'from' => \Auth::getUser(),
            'event' => $order->event,
            'group' => $group
        ];

        $view = \View::make('emails/tickets/cancellation', $attributes);

        $sender = $this->getSender($order->user, $order);
        if (!$sender) {
            return;
        }

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($sender);

        try {
            $apiClient->sendEmail(
                $order->event->name . ': We zijn er niet bij :(',
                $view->render(),
                $email
            );
        } catch (GuzzleException $e) {
            \Log::error($e);
        }
    }

    /**
     * The accounts user a ticket mail is sent as. Accounts' mail route is
     * users/{id}/mail (product credentials, but always on behalf of a user,
     * who becomes the reply-to), so a mail without any user cannot go out.
     *
     * Guest orders have no user: they are mailed on behalf of the admin who
     * is registering or cancelling the guest, which also makes the admin the
     * reply-to address.
     *
     * @param User|null $user
     * @param Order $order
     * @return User|null
     */
    protected function getSender(?User $user, Order $order)
    {
        $sender = $user ?: \Auth::getUser();

        if (!$sender) {
            \Log::warning('Ticket mail not sent: no accounts user to send it as', [
                'order' => $order->id
            ]);
            return null;
        }

        return $sender;
    }
}
