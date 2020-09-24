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

namespace App\Models;

use CatLab\Accounts\Client\ApiClient;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Class Order
 * @package App\Models
 */
class Order extends \CatLab\Charon\Laravel\Database\Model implements EuklesModel
{
    use SoftDeletes;

    const STATE_PENDING = 'PENDING';
    const STATE_ACCEPTED = 'ACCEPTED';
    const STATE_CANCELLED = 'CANCELLED';
    const STATE_REFUNDED = 'REFUNDED';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticketCategory()
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /**
     * Change the state of an order.
     * @param $state
     * @param bool $forceTrigger
     */
    public function changeState($state, $forceTrigger = false)
    {
        if ($this->state === $state && !$forceTrigger) {
            return;
        }

        $oldState = $this->state;

        $this->state = $state;
        $this->save();

        if ($state === self::STATE_ACCEPTED) {
            $this->onConfirmation();
        } elseif (
            $oldState === self::STATE_ACCEPTED &&
            (
                $state === self::STATE_CANCELLED ||
                $state === self::STATE_REFUNDED
            )
        ) {
            $this->onCancellation();
        }
    }

    /**
     * Is pending?
     * @return bool
     */
    public function isPending()
    {
        return $this->state === self::STATE_PENDING;
    }

    /**
     * Is accepted?
     * @return bool
     */
    public function isAccepted()
    {
        return $this->state === self::STATE_ACCEPTED;
    }

    /**
     * Is canceled?
     * @return bool
     */
    public function isCancelled()
    {
        return $this->state === self::STATE_CANCELLED ||
            $this->state === self::STATE_REFUNDED;
    }

    /**
     * @return string
     */
    public function getPayUrl()
    {
        // Set return url to the view component.
        $return = action('OrderController@thanks', [ $this->id ]);

        return \Config::get('services.catlab.url') . $this->pay_url . '?return=' . urlencode($return);
    }

    /**
     * @param bool $forceTrigger
     */
    public function synchronize($forceTrigger = false)
    {
        $catlabOrder = $this->getOrderData();
        $status = $catlabOrder['status'];

        if ($this->status !== $status || $forceTrigger) {
            // Don't go from "canceled" to "pending"...
            if (
                $this->status === Order::STATE_CANCELLED &&
                $status === Order::STATE_PENDING
            ) {
                // don't do anything!
            } else {
                $this->changeState($status, $forceTrigger);
            }
        }
    }

    /**
     * @param bool $expanded
     * @return mixed[]
     */
    public function getOrderData($expanded = false)
    {
        if ($expanded) {
            $client = new ApiClient($this->user);
        } else {
            $client = new ApiClient(null);
        }


        return $client->getOrder($this->catlab_order_id, $expanded);
    }

    /**
     *
     */
    public function onConfirmation()
    {
        // Send email
        try {
            foreach ($this->group->members as $member) {
                $this->sendConfirmationEmail($member);
            }
        } catch (LogicException $e) {
            \Log::error($e->getMessage());
        }

        // Track on ze eukles.
        $euklesEvent = \Eukles::createEvent(
            'event.order.confirmed',
            [
                'group' => $this->group,
                'event' => $this->event,
                'order' => $this
            ]
        )
            ->link($this->group, 'attends', $this->event)
            ->unlink($this->user, 'registering', $this->event);

        foreach ($this->group->members as $member) {
            if ($member->user) {
                $euklesEvent->setObject('member', $member->user);
            }
        }

        \Eukles::trackEvent($euklesEvent);
    }

    /**
     *
     */
    public function onCancellation()
    {
        // Cancel the sale in uitdb (if available)
        $uitPasService = \UitDb::getUitPasService();
        if ($uitPasService) {
            $uitPasService->registerOrderCancel($this);
        }

        // Send email
        foreach ($this->group->members as $member) {
            $this->sendCancellationEmail($member);
        }

        // Track on ze eukles.
        $euklesEvent =
            \Eukles::createEvent(
                'event.order.cancel',
                [
                    'group' => $this->group,
                    'event' => $this->event,
                    'order' => $this
                ]
            )
                ->unlink($this->group, 'attends', $this->event);

        foreach ($this->group->members as $member) {
            if ($member->user) {
                $euklesEvent->setObject('member', $member->user);
            }
        }

        \Eukles::trackEvent($euklesEvent);
    }

    /**
     * @param $member
     */
    public function sendConfirmationEmail($member)
    {
        /** @var Group $group */
        $group = $this->group;

        if (!$member->user) {
            return;
        }

        if (empty($member->user->email)) {
            return;
        }

        $attributes = [
            'from' => \Auth::getUser(),
            'event' => $this->event,
            'group' => $group
        ];

        $view = \View::make('emails/tickets/confirmation', $attributes);

        /** @var User $user */
        $user = $this->user;
        $apiClient = new ApiClient($user);

        $apiClient->sendEmail(
            $this->event->name . ': We zijn er bij!',
            $view->render(),
            $member->user->email
        );
    }

    /**
     * @param $member
     */
    public function sendCancellationEmail($member)
    {
        /** @var Group $group */
        $group = $this->group;

        if (!$member->user) {
            return;
        }

        if (empty($member->user->email)) {
            return;
        }

        $attributes = [
            'from' => \Auth::getUser(),
            'event' => $this->event,
            'group' => $group
        ];

        $view = \View::make('emails/tickets/cancellation', $attributes);

        /** @var User $user */
        $user = $this->user;
        $apiClient = new ApiClient($user);

        $apiClient->sendEmail(
            $this->event->name . ': We zijn er niet bij :(',
            $view->render(),
            $member->user->email
        );
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeAccepted($query)
    {
        return $query->where('state', '=', Order::STATE_ACCEPTED);
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopePending($query)
    {
        return $query->where('state', '=', Order::STATE_PENDING);
    }

    /**
     * @return array[]
     */
    public function getEuklesId()
    {
        return $this->id;
    }

    /**
     * @return array[]
     */
    public function getEuklesAttributes()
    {
        $data = $this->getOrderData(true);

        $price = $data['price'] + $data['vat'];

        return [
            'reference' => $data['reference'],
            'price' => $price,
            'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        ];
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'order';
    }
}
