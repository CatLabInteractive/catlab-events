<?php

namespace App\Listeners;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
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
     * @param Event $event
     * @param GroupMember $member
     */
    public function sendConfirmationEmail(Event $event, GroupMember $member)
    {
        /** @var Group $group */
        $group = $event->group;

        if (!$member->user) {
            return;
        }

        if (empty($member->user->email)) {
            return;
        }

        $attributes = [
            'from' => \Auth::getUser(),
            'event' => $event->event,
            'group' => $group
        ];

        $view = \View::make('emails/tickets/confirmation', $attributes);

        /** @var User $user */
        $user = $event->user;
        $apiClient = new ApiClient($user);

        $apiClient->sendEmail(
            $event->event->name . ': We zijn er bij!',
            $view->render(),
            $member->user->email
        );
    }

    /**
     * @param Order $order
     * @param GroupMember $member
     */
    public function sendCancellationEmail(Order $order, GroupMember $member)
    {
        /** @var Group $group */
        $group = $order->group;

        if (!$member->user) {
            return;
        }

        if (empty($member->user->email)) {
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
            $member->user->email
        );
    }
}
