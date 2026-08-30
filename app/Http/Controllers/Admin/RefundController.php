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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

/**
 * Class RefundController
 *
 * Refunding a paid order through accounts. The money really moves, so the
 * admin passes a confirmation page that shows the live amount and asks
 * them to type the order reference first.
 *
 * @package App\Http\Controllers\Admin
 */
class RefundController extends Controller
{
    /**
     * Whether this order can be refunded through the API at all.
     *
     * Orders created before the refund token existed cannot: accounts
     * answers 404 without one. Those are refunded from the accounts admin
     * panel instead.
     *
     * The catlab_order_id check also covers free tickets: they never get an
     * accounts order, so there is no payment to refund and no separate
     * price check is needed.
     *
     * @param Order $order
     * @return bool
     */
    public static function isRefundable(Order $order)
    {
        return $order->state === Order::STATE_ACCEPTED
            && $order->catlab_order_id
            && $order->refund_token;
    }

    /**
     * Show the confirmation page. Sends nothing.
     *
     * The Charon table action renders on every order row, so an order that
     * cannot be refunded here gets an explanation rather than a 404 -- a
     * dead link would just look broken.
     *
     * @param $orderId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function refund($orderId)
    {
        $order = $this->getOrderInOrganisation($orderId);

        if (!self::isRefundable($order)) {
            return $this->notRefundableView($order);
        }

        // This action renders on every order row, so an accounts outage
        // here must not 500 the page -- every click would otherwise be a
        // Whoops page instead of the explanation the design requires. The
        // identical call in processRefund() is guarded the same way.
        try {
            $orderData = $order->getOrderData(true);
        } catch (GuzzleException $e) {
            \Log::warning('Could not load live order data for the refund confirmation page', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'error' => $e->getMessage()
            ]);
            return $this->notRefundableView($order, 'Accounts is momenteel niet bereikbaar. Probeer het later opnieuw.');
        }

        $amount = isset($orderData['price']) ? $orderData['price'] : null;

        // A missing live price would print "€ 0,00" on a screen that says
        // "Dit is definitief." Accounts' amount binding would reject an
        // actual refund at that amount, so no money is at risk -- but a
        // false amount on this specific screen is not acceptable regardless.
        // A live price of exactly 0.0 is not "missing" and is left alone.
        if ($amount === null) {
            \Log::warning('Accounts returned no live price for the refund confirmation page', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
            ]);
            return $this->notRefundableView($order, 'De actuele prijs kon niet opgehaald worden bij accounts.');
        }

        return view('admin.orders.refund', [
            'order' => $order,
            'refundable' => true,
            'reference' => isset($orderData['reference']) ? $orderData['reference'] : null,
            'amount' => $amount,
            'unavailableReason' => null
        ]);
    }

    /**
     * The not-refundable rendering of the confirm page. `$reason`, when
     * given, overrides the reasons the view otherwise derives from the
     * order itself (state / no catlab order / predates the refund token) --
     * used for an accounts outage or a missing live price, neither of which
     * the view can tell from the order alone.
     *
     * @param Order $order
     * @param string|null $reason
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function notRefundableView(Order $order, $reason = null)
    {
        return view('admin.orders.refund', [
            'order' => $order,
            'refundable' => false,
            'reference' => null,
            'amount' => null,
            'unavailableReason' => $reason
        ]);
    }

    /**
     * Actually refund. Money moves on the accounts side.
     *
     * @param Request $request
     * @param $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processRefund(Request $request, $orderId)
    {
        $order = $this->getOrderInOrganisation($orderId);

        $back = redirect(action('Admin\OrderController@index'));

        if (!self::isRefundable($order)) {
            return $back->with('message', 'Deze order kan hier niet terugbetaald worden.');
        }

        // An accounts outage here means nothing has been attempted yet: no
        // money at risk, but the admin still deserves a flash message
        // instead of a 500.
        try {
            $orderData = $order->getOrderData(true);
        } catch (GuzzleException $e) {
            \Log::warning('Could not load live order data before refund', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'error' => $e->getMessage()
            ]);
            return $back->with('message', 'De order kon niet opgehaald worden bij accounts. Er is niets terugbetaald.');
        }

        $reference = isset($orderData['reference']) ? $orderData['reference'] : null;

        // Re-checked here, not just in the browser: disabling the button in
        // JS is a convenience, never the control.
        if (!$reference || trim((string) $request->input('reference')) !== $reference) {
            return $back->with('message', 'De referentie klopte niet. Er is niets terugbetaald.');
        }

        $amount = isset($orderData['price']) ? $orderData['price'] : null;
        $reason = mb_substr((string) $request->input('reason'), 0, 255);

        $apiClient = app(\App\Services\CatLabApiClientFactory::class)->forUser($order->user);

        \Log::info('Refunding order', [
            'order' => $order->id,
            'catlab_order_id' => $order->catlab_order_id,
            'amount' => $amount,
            'admin' => \Auth::id(),
        ]);

        try {
            $result = $apiClient->refundOrder($order->catlab_order_id, $order->refund_token, $amount, $reason ?: 'events admin');
        } catch (GuzzleException $e) {
            return $back->with('message', $this->describeFailure($order, $e, $amount));
        } catch (\Throwable $e) {
            // Not a GuzzleException: e.g. ApiClient::refundOrder() decoding
            // the response body only *after* a successful HTTP 200 and
            // throwing a plain \LogicException if that decode fails -- at
            // the exact moment the money has already moved -- or the client
            // failing to build the request at all because
            // services.catlab.client_id/client_secret are unset. Either way
            // whether the refund actually happened is as unknown as a
            // timeout, so this must never read as a definite failure.
            \Log::error('Order refund call raised a non-Guzzle error; outcome unknown', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'amount' => $amount,
                'admin' => \Auth::id(),
                'error' => $e->getMessage()
            ]);
            return $back->with('message', $this->reportUnknownOutcome($order));
        }

        \Log::info('Order refund succeeded', [
            'order' => $order->id,
            'catlab_order_id' => $order->catlab_order_id,
            'status' => isset($result['status']) ? $result['status'] : null,
        ]);

        // The money has already moved at this point: a failure to read the
        // state back must never turn a successful refund into a 500. Report
        // success regardless, with a note if the re-sync itself failed.
        try {
            $order->synchronize();
        } catch (\Throwable $e) {
            \Log::error('Order refund succeeded but the state re-sync failed', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'error' => $e->getMessage()
            ]);
            return $back->with(
                'message',
                'Order ' . $reference . ' is terugbetaald, maar de status kon niet opnieuw opgehaald worden. '
                    . 'Controleer de order in accounts.'
            );
        }

        return $back->with('message', 'Order ' . $reference . ' is terugbetaald.');
    }

    /**
     * Turn a failed refund call into something the admin can act on.
     *
     * A timeout is the dangerous one: the refund may well have gone
     * through, so we must not report failure and invite a second click. We
     * re-sync and report whatever accounts actually says -- and since
     * accounts is by definition unreachable or struggling on that path,
     * the re-sync itself must not be allowed to 500 either.
     *
     * @param Order $order
     * @param GuzzleException $e
     * @param float|null $amount the amount that was sent, for the log line
     * @return string
     */
    private function describeFailure(Order $order, GuzzleException $e, $amount = null)
    {
        $status = $e instanceof BadResponseException ? $e->getResponse()->getStatusCode() : null;

        \Log::warning('Order refund failed', [
            'order' => $order->id,
            'catlab_order_id' => $order->catlab_order_id,
            'amount' => $amount,
            'admin' => \Auth::id(),
            'status' => $status,
            'error' => $e->getMessage()
        ]);

        if ($status === 429) {
            return 'Er zijn te veel terugbetalingen gebeurd in korte tijd. Probeer het over een uur opnieuw.';
        }

        if ($status === 409) {
            return $this->reportRejectedByAccounts($order);
        }

        if ($status === 404 || $status === 401) {
            return 'De terugbetaling werd geweigerd. Controleer de order in accounts.';
        }

        // No response at all (timeout, connection error), a 5xx from
        // accounts itself, or a timeout status code emitted by an
        // intermediary rather than accounts' own guard chain (408, or
        // nginx's 499): whether the refund actually happened is genuinely
        // unknown.
        if ($status === null || $status >= 500 || $status === 408 || $status === 499) {
            return $this->reportUnknownOutcome($order);
        }

        // A response came back with a status this endpoint's documented
        // guard chain (401/404/409/429) does not cover -- e.g. 400/403/422.
        // Accounts rejected the request outright before touching the
        // gateway, so unlike the branch above there is no ambiguity: nothing
        // moved, and there is nothing new to re-sync.
        return 'De terugbetaling werd geweigerd door accounts (status ' . $status . '). Er is niets terugbetaald.';
    }

    /**
     * 409: accounts says this order can no longer be refunded (already
     * refunded, cancelled, amount mismatch, no payment, ...). Re-sync so the
     * order list reflects that -- but a failed re-sync must not turn this
     * into a 500.
     *
     * @param Order $order
     * @return string
     */
    private function reportRejectedByAccounts(Order $order)
    {
        try {
            $order->synchronize();
        } catch (\Throwable $e) {
            \Log::error('Order refund rejected by accounts, and the state re-sync failed', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'error' => $e->getMessage()
            ]);
            return 'Deze order kon niet meer terugbetaald worden. De status kon niet opnieuw opgehaald worden, '
                . 'controleer de order in accounts.';
        }

        return 'Deze order kon niet meer terugbetaald worden. De status is opnieuw opgehaald.';
    }

    /**
     * No usable response (timeout / connection error) or a 5xx from
     * accounts: whether the refund actually happened is genuinely unknown.
     * Re-sync so we can report the real state where possible, but a failed
     * re-sync must never turn into a 500 that reads as "this call itself
     * failed" -- that is exactly the false failure this whole path exists
     * to avoid.
     *
     * @param Order $order
     * @return string
     */
    private function reportUnknownOutcome(Order $order)
    {
        try {
            $order->synchronize();
        } catch (\Throwable $e) {
            \Log::error('Order refund outcome unknown, and the state re-sync failed', [
                'order' => $order->id,
                'catlab_order_id' => $order->catlab_order_id,
                'error' => $e->getMessage()
            ]);

            return 'Onbekend resultaat: de terugbetaling is mogelijk wel doorgegaan, maar de status kon niet '
                . 'opnieuw opgehaald worden. Controleer de order in accounts.';
        }

        // Accounts refuses a second refund of an order that already carries an
        // accepted refund, so retrying is normally caught rather than charged
        // twice. It is not a guarantee -- if the gateway processed the refund
        // but accounts never recorded it, nothing on this side can tell.
        return 'Onbekend resultaat: de terugbetaling is mogelijk wel doorgegaan. '
            . 'De status is opnieuw opgehaald; ze staat nu op ' . $order->fresh()->state . '. '
            . 'Een tweede poging wordt geweigerd als de eerste toch is doorgegaan, '
            . 'maar controleer de order in accounts als het onduidelijk blijft.';
    }

    /**
     * The order, or 404. Scoped to the acting admin's active organisation,
     * and to an admin role within it: `admin` is a global flag, so without
     * the organisation check an admin of one organisation could refund
     * another's order. getActiveOrganisation() itself returns any
     * organisation the user is attached to at *any* pivot role, so the
     * role is re-checked here via Organisation::isAdmin() -- the same check
     * every other order path in the panel authorizes through (OrderPolicy).
     * This one really is a 404 -- the order is none of this admin's
     * business.
     *
     * @param $orderId
     * @return Order
     */
    protected function getOrderInOrganisation($orderId)
    {
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$order->event || !$organisation
            || (int) $order->event->organisation_id !== (int) $organisation->id
            || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        return $order;
    }
}
