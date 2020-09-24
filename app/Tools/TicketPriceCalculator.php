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

namespace App\Tools;

use App\Models\Organisation;
use App\Models\TicketCategory;

/**
 * Class TicketPriceCalculator
 * @package App\Tools
 */
class TicketPriceCalculator
{
    /**
     * @var TicketCategory
     */
    protected $ticketCategory;

    /**
     * @var double
     */
    protected $subsidisedTariff = null;

    /**
     * TicketPriceCalculator constructor.
     * @param TicketCategory $ticketCategory
     */
    public function __construct(TicketCategory $ticketCategory)
    {
        $this->ticketCategory = $ticketCategory;
    }

    /**
     * @param $tariff
     */
    public function applySubsidisedTariff($tariff)
    {
        $this->subsidisedTariff = $tariff;
    }

    /**
     * @return double
     */
    protected function getBaseTicketPrice()
    {
        if ($this->subsidisedTariff !== null) {
            return $this->subsidisedTariff;
        } else {
            return $this->ticketCategory->price;
        }
    }

    /**
     * Calculate the transaction fee to be paid on this ticket type.
     * @param bool $includeVat
     * @return float
     */
    public function calculateTransactionFee($includeVat = false)
    {
        /** @var Organisation $organisation */
        $organisation = $this->ticketCategory->event->organisation;
        $percentage = $organisation->getTransactionFeeFactor();

        $fixed = $organisation->getTransactionFeeFixed();

        if ($this->ticketCategory->event->include_ticket_fee) {
            $base = ($this->getBaseTicketPrice() / (1 + $percentage)) - $fixed;
            $variable = $base * $percentage;
        } else {
            $variable = $this->getBaseTicketPrice() * $percentage;
        }

        $fee = round(($fixed + $variable), 2);
        $fee = max($organisation->getTransactionFeeMinimum(), $fee);

        if ($includeVat) {
            $fee += $this->calculateTransactionFeeVat();
        }

        // transaction fee should always be smaller than price.
        $fee = min($this->getBaseTicketPrice(), $fee);

        return round($fee, 2);
    }

    /**
     * @return float
     */
    public function calculateTransactionFeeVat()
    {
        $fee = $this->calculateTransactionFee(false);

        /** @var Organisation $organisation */
        $organisation = $this->ticketCategory->event->organisation;

        $vat = $fee * $organisation->getFeeVatFactor();
        $vat = round($vat, 2);

        return $vat;
    }

    /**
     * @param bool $includeVat
     * @return float
     */
    public function getTicketPrice($includeVat = true)
    {
        if ($this->ticketCategory->event->include_ticket_fee) {
            $price = $this->getBaseTicketPrice() - $this->calculateTransactionFee(true);
        } else {
            $price = $this->getBaseTicketPrice();
        }

        if (!$includeVat) {
            $vatFactor = 1 + ($this->ticketCategory->event->vat_percentage / 100);
            $price /= $vatFactor;
        }

        return round($price, 2);
    }

    /**
     *
     */
    public function getTicketPriceVat()
    {
        return round($this->getTicketPrice(true) - $this->getTicketPrice(false), 2);
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
        $total =  $this->getTicketPrice(true) + $this->calculateTransactionFee(true);
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
        return $this->toMoney($this->getBaseTicketPrice() + $this->calculateTransactionFee(true));
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
