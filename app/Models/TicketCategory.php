<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TicketCategory
 * @package App\Models
 */
class TicketCategory extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $table = 'event_ticket_categories';

    /**
     * @var
     */
    protected $availableError = null;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param Group $group
     * @return Order
     */
    public function createOrder(Group $group)
    {
        $order = new Order();

        $order->event()->associate($this->event);
        $order->group()->associate($group);
        $order->ticketCategory()->associate($this);
        $order->user()->associate(\Auth::getUser());

        return $order;
    }

    /**
     * Calculate the transaction fee to be paid on this ticket type.
     * @param bool $includeVat
     * @return float
     */
    public function calculateTransactionFee($includeVat = false)
    {
        /** @var Organisation $organisation */
        $organisation = $this->event->organisation;
        $percentage = $organisation->getTransactionFeeFactor();

        $fixed = $organisation->getTransactionFeeFixed();

        if ($this->event->include_ticket_fee) {
            $base = ($this->price / (1 + $percentage)) - $fixed;
            $variable = $base * $percentage;
        } else {
            $variable = $this->price * $percentage;
        }

        $fee = round(($fixed + $variable), 2);
        $fee = max($organisation->getTransactionFeeMinimum(), $fee);

        if ($includeVat) {
            $fee += $this->calculateTransactionVeeVat();
        }

        // transaction fee should always be smaller than price.
        $fee = min($this->price, $fee);

        return round($fee, 2);
    }

    /**
     * @return float
     */
    public function calculateTransactionVeeVat()
    {
        $fee = $this->calculateTransactionFee(false);

        /** @var Organisation $organisation */
        $organisation = $this->event->organisation;

        $vat = $fee * $organisation->getFeeVatFactor();
        $vat = round($vat, 2);

        return $vat;
    }

    /**
     * Get the actual price of the ticket.
     */
    public function getTicketPrice()
    {
        if ($this->event->include_ticket_fee) {
            return round($this->price - $this->calculateTransactionFee(true), 2);
        }
        return round($this->price, 2);
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
        $total =  $this->getTicketPrice() + $this->calculateTransactionFee(true);
        return round($total, 2);
    }

    /**
     * @return string
     */
    public function getFormattedPrice()
    {
        return $this->toMoney($this->getTicketPrice());
    }

    /**
     * @return string
     */
    public function getFormattedTransactionFee()
    {
        return $this->toMoney($this->calculateTransactionFee(true));
    }

    /**
     * @return string
     */
    public function getFormattedTotalPrice()
    {
        return $this->toMoney($this->getTotalPrice());
    }

    /**
     * @return string
     */
    public function getFormattedTotalCost()
    {
        return $this->toMoney($this->price + $this->calculateTransactionFee(true));
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isAvailable()
    {
        if (!$this->event->canRegister()) {
            return false;
        }

        return $this->getAvailableError() === false;
    }

    /**
     * Same as isAvailable, but returns true for tickets
     * that are not on sale yet.
     */
    public function willBecomeAvailable()
    {
        $available = $this->countAvailableTickets();
        if ($available !== null && $available <= 0) {
            // sold out
            return false;
        }

        elseif ($this->end_date && ($this->end_date < new DateTime())) {
            return false;
        }

        else {
            return true;
        }
    }

    /**
     * @return array|null
     * @throws \Exception
     */
    public function getAvailableError()
    {
        if (!isset($this->availableError)) {

            $available = $this->countAvailableTickets();
            if ($available !== null && $available <= 0) {
                $this->availableError = [ 'Uitverkocht.' ];
            }

            elseif ($this->start_date && ($this->start_date > new DateTime())) {
                $this->availableError = [ 'Vanaf %s', $this->start_date ];
            }

            elseif ($this->end_date && ($this->end_date < new DateTime())) {
                //$this->availableError = [ 'Verlopen sinds %s', $this->end_date ];
                $this->availableError = [ 'Te laat' ];
            }

            else {
                $this->availableError = false;
            }
        }

        return $this->availableError;
    }

    /**
     * @return array
     */
    public function getAvailabilityWarnings()
    {
        $warnings = [];

        if ($this->max_tickets) {
            $warnings[] = [ 'Nog %s beschikbaar.', $this->countAvailableTickets() ];
        }

        if ($this->end_date) {
            $warnings[] = [ 'Registreer vóór %s.', $this->end_date ];
        }

        return $warnings;
    }

    /**
     * @param $error
     * @return string
     */
    public function errorToString($error)
    {
        if ($error && count($error) > 1) {
            $arguments = [];
            for ($i = 1; $i < count($error); $i++) {
                $att = $error[$i];
                if ($att instanceof DateTime) {
                    $arguments[] = $att->format('d/m/Y H:i');
                } else {
                    $arguments[] = $att;
                }
            }
            return vsprintf($error[0], $arguments);
        } else {
            return $error[0];
        }
    }

    /**
     * @return int
     */
    public function countSoldTickets()
    {
        return $this
            ->orders()
            ->whereIn('state', [ Order::STATE_ACCEPTED, Order::STATE_PENDING ])
            ->count()
        ;
    }

    /**
     * @return int|null
     */
    public function countAvailableTickets()
    {
        $eventAvailable = $this->event->countAvailableTickets();
        if ($eventAvailable !== null && $eventAvailable <= 0) {
            return 0;
        }

        if ($this->max_tickets) {
            if ($eventAvailable !== null) {
                return min($eventAvailable, $this->max_tickets - $this->countSoldTickets());
            } else {
                return $this->max_tickets - $this->countSoldTickets();
            }
        } elseif ($eventAvailable !== null) {
            return $eventAvailable;
        } else {
            return null;
        }
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        $out = [
            '@type' => 'Offer',
            'description' => $this->name,
            'url' => action('EventController@register', [ $this->event->id, $this->id ]),
            'price' => $this->getTotalPrice(),
            'priceCurrency' => 'EUR',
            'availability' => 'http://schema.org/SoldOut'
        ];

        if ($this->countAvailableTickets() > 0) {
            $out['availability'] = 'http://schema.org/InStock';
        }

        if ($this->start_date) {
            $out['validFrom'] = $this->start_date->format('c');
        } else {
            $out['validFrom'] = $this->created_at->format('c');
        }

        if ($this->end_date) {
            $out['validThrough'] = $this->end_date->format('c');
        }

        return $out;
    }

    /**
     * @param $amount
     * @return string
     */
    protected function toMoney($amount)
    {
        return '€ ' . number_format($amount, 2, ',', '');
    }
}