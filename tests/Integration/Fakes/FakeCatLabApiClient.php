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
        return [
            'id' => $id,
            'status' => $this->orderStatus,
            'price' => 10.0,
            'reference' => 'TEST-' . $id,
        ];
    }

    public function sendEmail($subject, $body, $target = null)
    {
        $this->sendEmailCalls[] = ['subject' => $subject, 'target' => $target];

        return true;
    }
}
