<?php

namespace Tests\Integration\Fakes;

use CatLab\Accounts\Client\ApiClient;

class FakeCatLabApiClient extends ApiClient
{
    public $createOrderCalls = [];
    public $sendEmailCalls = [];
    public $orderStatus = 'PENDING';
    public $nextOrderId = 4242;

    public function __construct()
    {
        parent::__construct(null);
    }

    public function createOrder($data)
    {
        $this->createOrderCalls[] = $data;

        return [
            'id' => $this->nextOrderId,
            'payUrl' => 'https://pay.example.com/order/' . $this->nextOrderId,
        ];
    }

    public function getOrder($id, $expanded = false)
    {
        $order = [
            'id' => $id,
            'status' => $this->orderStatus,
            'price' => 10.0,
            'reference' => 'TEST-' . $id,
        ];

        if ($expanded) {
            // Shape of accounts' ?expanded=1 payload, as far as the order
            // views read it (orders/view.blade.php iterates `items`).
            $order['items'] = [
                [ 'name' => 'Test ticket', 'amount' => 1, 'price' => 8.26, 'vat' => 1.74 ],
            ];
            $order['originalItems'] = $order['items'];
            $order['originalPrice'] = 10.0;
            $order['discount'] = 0.0;
            $order['vat'] = 1.74;
            $order['price_novat'] = 8.26;
        }

        return $order;
    }

    /** @var \Throwable|null thrown by sendEmail() to simulate accounts failing (429, timeout, ...) */
    public $sendEmailException = null;

    public function sendEmail($subject, $body, $target = null)
    {
        if ($this->sendEmailException) {
            throw $this->sendEmailException;
        }

        $this->sendEmailCalls[] = ['subject' => $subject, 'target' => $target];

        return true;
    }
}
