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

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Tools\TicketPriceCalculator;
use CatLab\Accounts\Client\ApiClient;
use CatLab\Eukles\Client\Interfaces\EuklesModel;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
            $state === self::STATE_CANCELLED ||
            $state === self::STATE_REFUNDED
        ) {
            $this->onCancellation($oldState === self::STATE_ACCEPTED);
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

        if (Str::startsWith(Str::lower($this->pay_url), 'http')) {
            return $this->pay_url . '?return=' . urlencode($return);
        } else {
            return \Config::get('services.catlab.url') . $this->pay_url . '?return=' . urlencode($return);
        }
    }

    /**
     * @param bool $forceTrigger
     */
    public function synchronize($forceTrigger = false)
    {
        // No catlab id? Order was not registered succesfully, so cancel it now.
        if (!$this->catlab_order_id) {
            $this->changeState(self::STATE_CANCELLED);
            return;
        }

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
        // Fetch anything that we might need.
        $this->fetchPlayUrl();

        event(new OrderConfirmed($this));
    }

    /**
     * @param boolean $wasAccepted
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onCancellation($wasAccepted = false)
    {
        // Cancel the sale in uitdb (if available)
        // Always do this, even if the order was never confirmed.
        $uitPasService = \UitDb::getUitPasService();
        if ($uitPasService) {
            $uitPasService->registerOrderCancel($this);
        }

        event(new OrderCancelled($this, $wasAccepted));
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

        $price = $data['price'];

        return [
            'reference' => $data['reference'],
            'price' => $price,
            'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'receipt' => isset($data['receipt']) ? $data['receipt'] : null,
            'playLink' => $this->play_link
        ];
    }

    /**
     * @return string
     */
    public function getEuklesType()
    {
        return 'order';
    }

    /**
     * @param float $tariff
     * @param string $saleId
     */
    public function setUiTPASTariff(float $tariff, string $saleId)
    {
        $this->subsidised_tariff = $tariff;
        $this->uitpas_sale_id = $saleId;
    }

    /**
     * @return TicketPriceCalculator
     */
    public function getTicketPriceCalculator()
    {
        $priceCalculator = new TicketPriceCalculator($this->ticketCategory);
        if ($this->subsidised_tariff) {
            $priceCalculator->applySubsidisedTariff($this->subsidised_tariff);
        }
        return $priceCalculator;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchPlayUrl()
    {
        /** @var TicketCategory $ticketCategory */
        $ticketCategory = $this->ticketCategory;

        /** @var Event $event */
        $event = $ticketCategory->event;

        if (!$event->campaign_id) {
            return;
        }

        $maxPlayers = null;
        if ($ticketCategory->max_players) {
            $maxPlayers = $ticketCategory->max_players;
        }

        $url = config('services.quizwitz.url');
        $url .= '/campaigns/' . $event->campaign_id . '/campaign-links';
        $url .= '?output=json&client=' . urlencode(config('services.quizwitz.apiClient'));

        $client = new Client();
        $response = $client->post($url, [ 'form_params' => [ 'maxPlayers'  => $maxPlayers ] ]);

        $data = json_decode($response->getBody(), true);
        $playLink = $data['campaignLink']['url'];

        $additionalParameter = [
            'lang' => 'nl'
        ];

        if (Str::contains($playLink, '?')) {
            $playLink .= '&';
        } else {
            $playLink .= '?';
        }

        $playLink .= http_build_query($additionalParameter);

        $this->play_link = $playLink;
        $this->save();
    }
}
