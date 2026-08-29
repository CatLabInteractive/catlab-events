<?php

namespace Tests\Integration\Fakes;

use CatLab\Accounts\Client\ApiClient;

class FakeCatLabApiClient extends ApiClient
{
    public $createOrderCalls = [];
    public $sendEmailCalls = [];
    public $orderStatus = 'PENDING';
    public $nextOrderId = 4242;
    public $refundOrderCalls = [];
    public $nextRefundToken = 'faketoken0123456789abcd';
    public $refundStatus = 'REFUNDED';

    /** @var \Throwable|null thrown by refundOrder() to simulate accounts failing (409, 429, timeout, ...) */
    public $refundOrderException = null;

    /**
     * @var \Throwable|null thrown by getOrder() when called without
     * `$expanded` -- i.e. by Order::synchronize() -- to simulate accounts
     * staying unreachable through the re-sync that follows a failed
     * refundOrder() call, rather than conveniently recovering in between.
     * The expanded lookup (the live reference/amount check made before the
     * refund is attempted) is left alone, same as a real outage that starts
     * only once the refund call itself goes out.
     */
    public $getOrderException = null;

    /**
     * @var \Throwable|null thrown by getOrder() when called *with*
     * `$expanded` -- i.e. by the refund confirmation page (GET) and by
     * processRefund()'s pre-flight reference/amount check (POST) -- to
     * simulate accounts being unreachable for that call specifically,
     * independently of $getOrderException above.
     */
    public $getOrderExpandedException = null;

    /**
     * @var float|null the live price getOrder() reports. Set to null to
     * simulate accounts returning no price for the order.
     */
    public $price = 10.0;

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
            'refundToken' => $this->nextRefundToken,
        ];
    }

    public function getOrder($id, $expanded = false)
    {
        if ($expanded && $this->getOrderExpandedException) {
            throw $this->getOrderExpandedException;
        }

        if ($this->getOrderException && !$expanded) {
            throw $this->getOrderException;
        }

        $order = [
            'id' => $id,
            'status' => $this->orderStatus,
            'price' => $this->price,
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

    public function refundOrder($orderId, $refundToken, $amount, $reason = 'api')
    {
        if ($this->refundOrderException) {
            throw $this->refundOrderException;
        }

        $this->refundOrderCalls[] = [
            'orderId' => $orderId,
            'refundToken' => $refundToken,
            'amount' => $amount,
            'reason' => $reason,
        ];

        $this->orderStatus = $this->refundStatus;

        return [ 'id' => $orderId, 'status' => $this->refundStatus ];
    }
}
