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

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CatLabApiClientFactory;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

/**
 * Class ExpirePendingOrders
 *
 * Releases the seats held by unpaid orders (guest checkout spec 2026-11-17).
 * Each order is cancelled at accounts first, so its pay URL stops working
 * before the seat is given to someone else; an order whose payment is still
 * in progress at a gateway (accounts answers 409) is left for a later run.
 *
 * Event::cancelTimeoutPendingOrders() (a local-only sweep on event view)
 * still exists alongside this.
 *
 * @package App\Console\Commands
 */
class ExpirePendingOrders extends Command
{
    const GUEST_TTL_MINUTES = 30;
    const TTL_MINUTES = 60;
    const LOOKBACK_DAYS = 7;

    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel unpaid orders (at accounts and locally) so their seats are released';

    public function handle()
    {
        $now = new \DateTimeImmutable();

        $orders = Order::query()
            ->where('state', '=', Order::STATE_PENDING)
            ->where('created_at', '>', $now->sub(new \DateInterval('P' . self::LOOKBACK_DAYS . 'D')))
            ->where(function ($query) use ($now) {
                $query
                    ->where(function ($q) use ($now) {
                        $q->where('is_guest_checkout', '=', true)
                            ->where('created_at', '<', $now->sub(new \DateInterval('PT' . self::GUEST_TTL_MINUTES . 'M')));
                    })
                    ->orWhere(function ($q) use ($now) {
                        $q->where('is_guest_checkout', '=', false)
                            ->where('created_at', '<', $now->sub(new \DateInterval('PT' . self::TTL_MINUTES . 'M')));
                    });
            })
            ->orderBy('id')
            ->get();

        $cancelled = 0;
        foreach ($orders as $order) {
            if ($this->expire($order)) {
                $cancelled++;
            }
        }

        $this->info('Expired ' . $cancelled . ' of ' . count($orders) . ' unpaid orders.');
        return 0;
    }

    /**
     * @param Order $order
     * @return bool whether the order is now cancelled
     */
    protected function expire(Order $order): bool
    {
        // Paid in the meantime? Then the sync accepts it.
        try {
            $order->synchronize();
        } catch (\Throwable $e) {
            \Log::warning('orders:expire-pending: sync failed', [ 'order' => $order->id, 'error' => $e->getMessage() ]);
            return false;
        }

        if (!$order->isPending()) {
            return $order->isCancelled();
        }

        if ($order->catlab_order_id) {
            try {
                $result = app(CatLabApiClientFactory::class)->forUser(null)->cancelOrder($order->catlab_order_id);
            } catch (ClientException $e) {
                if ($e->getResponse() && $e->getResponse()->getStatusCode() === 409) {
                    // A payment is in progress at a gateway: try again next run.
                    return false;
                }
                \Log::warning('orders:expire-pending: cancel at accounts failed', [ 'order' => $order->id, 'error' => $e->getMessage() ]);
                return false;
            } catch (\Throwable $e) {
                \Log::warning('orders:expire-pending: cancel at accounts failed', [ 'order' => $order->id, 'error' => $e->getMessage() ]);
                return false;
            }

            // Accounts may have accepted it just now.
            if (isset($result['status']) && $result['status'] !== Order::STATE_CANCELLED) {
                try {
                    $order->synchronize();
                } catch (\Throwable $e) {
                    \Log::warning('orders:expire-pending: sync failed', [ 'order' => $order->id, 'error' => $e->getMessage() ]);
                }
                return $order->isCancelled();
            }
        }

        try {
            $order->changeState(Order::STATE_CANCELLED);
        } catch (\Throwable $e) {
            \Log::error('orders:expire-pending: local cancel failed', [ 'order' => $order->id, 'error' => $e->getMessage() ]);
            return false;
        }

        return true;
    }
}
