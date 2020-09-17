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

namespace App\Http\Controllers;

use Paynl\Result\Transaction\Transaction;

/**
 * Class DonateController
 * @package App\Http\Controllers
 */
class DonateController
{
    public function __construct()
    {
        // Replace tokenCode apitoken and serviceId with your own.
        \Paynl\Config::setTokenCode(config('services.pay.tokenCode'));
        \Paynl\Config::setApiToken(config('services.pay.apiToken'));
        \Paynl\Config::setServiceId(config('services.pay.serviceId'));
    }

    /**
     * Donate
     */
    public function donate()
    {
        $parameters = [
            'amount' => 1000,
            'amount_min' => 500,
            'country' => 'be',
            'extra1[Jouw (quiz)naam]' => '',
            'extra2[Jouw boodschap]' => '',
            //'exchangeUrl' => action('DonateController@callback')
        ];

        $url = 'https://www.pay.nl/doneren/SL-5213-8581/0Lcd1cc/?' . http_build_query($parameters);
        return redirect($url);
    }

    /**
     *
     */
    public function callback()
    {
        $transaction = \Paynl\Transaction::getForExchange();

        if ($transaction->isPaid() || $transaction->isAuthorized()) {
            // process the payment
            // Track on ze eukles.
            \Eukles::trackEvent(
                \Eukles::createEvent(
                    'donation.success',
                    $this->getEuklesTransactionData($transaction)
                )
            );

        } elseif ($transaction->isCanceled()) {
            // payment canceled, restock items
            \Eukles::trackEvent(
                \Eukles::createEvent(
                    'donation.canceled',
                    $this->getEuklesTransactionData($transaction)
                )
            );
        }

        // always start your response with TRUE|
        echo "TRUE| ";

        // Optionally you can send a message after TRUE|, you can view these messages in the logs.
        // https://admin.pay.nl/logs/payment_state
        echo ($transaction->isPaid() || $transaction->isAuthorized()) ? 'Paid' : 'Not paid';
    }

    /**
     * @param Transaction $transaction
     * @return array[]
     */
    protected function getEuklesTransactionData(Transaction $transaction)
    {
        return [
            'donation' => [
                'type' => 'donation',
                'amount' => $transaction->getAmount(),
                'currency' => $transaction->getPaidCurrency(),
                'message' => $transaction->getExtra2()
            ],
            'from' => [
                'type' => 'donor',
                'name' => $transaction->getExtra1()
            ]
        ];
    }
}
