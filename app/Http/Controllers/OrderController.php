<?php

namespace App\Http\Controllers;

use App\Models\Order;
use CatLab\Accounts\Client\ApiClient;

/**
 * Class OrderController
 * @package App\Http\Controllers
 */
class OrderController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $user = \Auth::getUser();

        $orders = $user
            ->orders()
            ->where('state', '!=', Order::STATE_CANCELLED)
            ->orderBy('id', 'desc')
        ;

        return view(
            'orders/index',
            [
                'orders' => $orders->get()
            ]
        );
    }

    /**
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);
        $order->synchronize();

        $orderData = $order->getOrderData(true);
        return view(
            'orders/view',
            [
                'order' => $order,
                'orderData' => $orderData
            ]
        );
    }

    /**
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function thanks($orderId)
    {
        $forceTracker = \Request::get('forceTracking');
        $forceTrigger = false;

        if (\Auth::user() && \Auth::user()->isAdmin()) {
            $forceTrigger = !!\Request::get('forceTrigger');
        }

        /** @var Order $order */
        $order = Order::findOrFail($orderId);
        $order->synchronize($forceTrigger);

        $trackConversion = !!$forceTracker;
        if ($order->isAccepted() && !$order->tracker_sent) {
            $trackConversion = true;
            $order->tracker_sent = 1;
            $order->save();
        }

        $retryFormAction = action('EventController@processRegister', [ $order->event->id, $order->ticketCategory->id ] );
        $retryFormInput = [
            'group' => $order->group->id
        ];

        return view(
            'orders/thanks',
            [
                'order' => $order,
                'trackConversion' => $trackConversion,
                'redirectUrl' => action('OrderController@thanks', [ $orderId ]),
                'retryFormAction' => $retryFormAction,
                'retryFormInput' => $retryFormInput
            ]
        );
    }

    /**
     * @param $orderId
     * @return string
     */
    public function sync($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);
        $order->synchronize();

        return \Response::json([ 'success' => 1 ]);
    }
}
