<?php

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
