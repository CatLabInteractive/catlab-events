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

namespace App\UitDB;

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketCategory;
use App\Tools\TicketPriceCalculator;
use App\UitDB\Exceptions\InvalidCardException;
use App\UitDB\Exceptions\InvalidEventException;
use App\UitDB\Exceptions\PriceClassNotFound;
use App\UitDB\Exceptions\UitPASAlreadyUsed;
use App\UitDB\Exceptions\UitPASException;
use App\UitDB\Exceptions\UiTPASGenericCardError;
use GuzzleHttp\Exception\RequestException;

/**
 * Class UiTPASVerifier
 * @package App\Tools
 */
class UiTPASVerifier
{
    /**
     * @var UitDatabankService
     */
    private $uitDatabank;

    /**
     * UitPASVerifier constructor.
     * @param UitDatabankService $uitDatabank
     */
    public function __construct(UitDatabankService $uitDatabank)
    {
        $this->uitDatabank = $uitDatabank;
    }

    /**
     * Check an UiTPas card and adapt the price calculator to match the provided (discounted) tariff.
     * @param TicketCategory $ticketCategory
     * @param TicketPriceCalculator $ticketPriceCalculator
     * @param $cardNumber
     * @return TicketPriceCalculator
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function applyUitPasTariff(TicketCategory $ticketCategory, TicketPriceCalculator $ticketPriceCalculator, $cardNumber)
    {
        /** @var Event $event */
        $event = $ticketCategory->event;

        if ($this->uitDatabank->hasClientCredentials()) {
            return $this->applyUitPasTariffV2($ticketCategory, $ticketPriceCalculator, $cardNumber);
        }

        return $this->applyUitPasTariffLegacy($ticketCategory, $ticketPriceCalculator, $cardNumber);
    }

    /**
     * @param Order $order
     * @param $cardNumber
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function registerTicketSale(Order $order, $cardNumber)
    {
        if ($this->uitDatabank->hasClientCredentials()) {
            $this->registerTicketSaleV2($order, $cardNumber);
            return;
        }

        $this->registerTicketSaleLegacy($order, $cardNumber);
    }

    /**
     * @param Order $order
     * @throws InvalidCardException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function registerOrderCancel(Order $order)
    {
        if (!$order->uitpas_sale_id) {
            return;
        }

        if ($this->uitDatabank->hasClientCredentials()) {
            $this->registerOrderCancelV2($order);
            return;
        }

        $this->registerOrderCancelLegacy($order);
    }

    /**
     * @param TicketCategory $ticketCategory
     * @return bool
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function hasApplicableUitPasPrice(TicketCategory $ticketCategory)
    {
        if ($this->uitDatabank->hasClientCredentials()) {
            return $this->hasApplicableUitPasPriceV2($ticketCategory);
        }

        return $this->hasApplicableUitPasPriceLegacy($ticketCategory);
    }

    /**
     * @param Event $event
     * @return bool
     */
    public function canCheckIn(Event $event)
    {
        $organisation = $event->organisation;
        if (!$organisation->uitdb_identifier && !$this->uitDatabank->hasClientCredentials()) {
            return false;
        }

        return true;
    }

    /**
     * @param Event $event
     * @param $uitpas
     * @return bool
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uitPASCheckin(Event $event, $uitpas)
    {
        if ($this->uitDatabank->hasClientCredentials()) {
            return $this->uitPASCheckinV2($event, $uitpas);
        }

        return $this->uitPASCheckinLegacy($event, $uitpas);
    }

    // ==========================================
    // New UiTPAS API v2 (JSON/OAuth2) methods
    // ==========================================

    /**
     * Apply UiTPAS tariff using the new v2 API.
     * @param TicketCategory $ticketCategory
     * @param TicketPriceCalculator $ticketPriceCalculator
     * @param $cardNumber
     * @return TicketPriceCalculator
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function applyUitPasTariffV2(TicketCategory $ticketCategory, TicketPriceCalculator $ticketPriceCalculator, $cardNumber)
    {
        /** @var Event $event */
        $event = $ticketCategory->event;

        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        $tariffs = $this->getUitPasTariffsV2($event, $cardNumber);
        $applicableTariff = $this->findApplicableTariffV2($ticketCategory, $tariffs);

        if ($applicableTariff === null) {
            return $ticketPriceCalculator;
        }

        $ticketPriceCalculator->applySubsidisedTariff(floatval($applicableTariff['price']));

        return $ticketPriceCalculator;
    }

    /**
     * Register a ticket sale using the new v2 API.
     * @param Order $order
     * @param $cardNumber
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerTicketSaleV2(Order $order, $cardNumber)
    {
        /** @var Event $event */
        $event = $order->event;

        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        $tariffs = $this->getUitPasTariffsV2($event, $cardNumber);
        $applicableTariff = $this->findApplicableTariffV2($order->ticketCategory, $tariffs);

        if ($applicableTariff === null) {
            return;
        }

        try {
            $response = $this->uitDatabank->uitpasApiRequest(
                'POST',
                '/ticket-sales',
                [
                    'json' => [
                        'eventId' => $event->getUitDBId(),
                        'uitpasNumber' => $cardNumber,
                        'tariff' => [
                            'id' => $applicableTariff['id'],
                        ],
                    ]
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorV2($e->getResponse());
            }
            throw $e;
        }

        if (isset($response['id'])) {
            $tariffPrice = floatval($applicableTariff['price']);
            $order->setUiTPASTariff($tariffPrice, $response['id']);
        }
    }

    /**
     * Cancel a ticket sale using the new v2 API.
     * @param Order $order
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerOrderCancelV2(Order $order)
    {
        try {
            $this->uitDatabank->uitpasApiRequest(
                'DELETE',
                '/ticket-sales/' . $order->uitpas_sale_id
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorV2($e->getResponse());
            }
            throw $e;
        }
    }

    /**
     * Check if there's an applicable UiTPAS price using v2 API.
     * @param TicketCategory $ticketCategory
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function hasApplicableUitPasPriceV2(TicketCategory $ticketCategory)
    {
        /** @var Event $event */
        $event = $ticketCategory->event;

        if (!$event->getUitDBId()) {
            return false;
        }

        try {
            $tariffs = $this->getUitPasTariffsV2($event);
            $applicableTariff = $this->findApplicableTariffV2($ticketCategory, $tariffs);
            return $applicableTariff !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform UiTPAS check-in using the v2 API.
     * @param Event $event
     * @param $uitpas
     * @return bool
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function uitPASCheckinV2(Event $event, $uitpas)
    {
        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        try {
            $this->uitDatabank->uitpasApiRequest(
                'POST',
                '/events/' . $event->getUitDBId() . '/checkins',
                [
                    'json' => [
                        'uitpasNumber' => $uitpas,
                    ]
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorV2($e->getResponse());
            }
            throw $e;
        }

        return true;
    }

    /**
     * Get UiTPAS tariffs for an event using the v2 API.
     * @param Event $event
     * @param string|null $cardNumber
     * @return array
     * @throws InvalidEventException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUitPasTariffsV2(Event $event, $cardNumber = null)
    {
        $query = [];
        if ($cardNumber) {
            $query['uitpasNumber'] = $cardNumber;
        }

        try {
            $response = $this->uitDatabank->uitpasApiRequest(
                'GET',
                '/events/' . $event->getUitDBId() . '/tariffs',
                [
                    'query' => $query
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode === 404) {
                    throw InvalidEventException::make();
                }
                $this->handleApiErrorV2($e->getResponse());
            }
            throw $e;
        }

        return $response ?? [];
    }

    /**
     * Find the applicable tariff for a ticket category from the v2 API response.
     * @param TicketCategory $ticketCategory
     * @param array $tariffs
     * @return array|null
     */
    protected function findApplicableTariffV2(TicketCategory $ticketCategory, array $tariffs)
    {
        if (empty($tariffs)) {
            return null;
        }

        // Search by name match first
        foreach ($tariffs as $tariff) {
            if (isset($tariff['name']) && $tariff['name'] === $ticketCategory->name) {
                return $tariff;
            }
        }

        // Search by price match
        $price = floatval($ticketCategory->price);
        foreach ($tariffs as $tariff) {
            if (isset($tariff['price']) && floatval($tariff['price']) === $price) {
                return $tariff;
            }
        }

        // Return first available tariff if only one exists
        if (count($tariffs) === 1) {
            return $tariffs[0];
        }

        return null;
    }

    /**
     * Handle API error from the v2 JSON API.
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws InvalidCardException
     * @throws UitPASException
     */
    protected function handleApiErrorV2($response)
    {
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        $type = $data['type'] ?? '';
        $title = $data['title'] ?? 'Unknown error';
        $detail = $data['detail'] ?? $body;

        switch ($type) {
            case 'https://api.publiq.be/probs/uitpas/invalid-card':
                throw new InvalidCardException('Je UitPAS kaartnummer kon niet worden herkend: ' . $detail);

            case 'https://api.publiq.be/probs/uitpas/maximum-reached':
                throw new UitPASException('Maximum aantal UiTPAS korting bereikt.');

            default:
                throw new UitPASException($title . ': ' . $detail);
        }
    }

    // ==========================================
    // Legacy UiTPAS API v1 (XML/OAuth1) methods
    // ==========================================

    /**
     * @param TicketCategory $ticketCategory
     * @param TicketPriceCalculator $ticketPriceCalculator
     * @param $cardNumber
     * @return TicketPriceCalculator
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function applyUitPasTariffLegacy(TicketCategory $ticketCategory, TicketPriceCalculator $ticketPriceCalculator, $cardNumber)
    {
        /** @var Event $event */
        $event = $ticketCategory->event;

        $uitPasEvent = $this->getUitPasEventLegacy($event, $cardNumber);
        $priceClass = $this->getApplicableUitPASPriceLegacy($ticketCategory, $uitPasEvent);

        if ($priceClass === null) {
            return $ticketPriceCalculator;
        }

        $ticketPriceCalculator->applySubsidisedTariff(floatval($priceClass->tariff));

        return $ticketPriceCalculator;
    }

    /**
     * @param Order $order
     * @param $cardNumber
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws PriceClassNotFound
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerTicketSaleLegacy(Order $order, $cardNumber)
    {
        /** @var Event $event */
        $event = $order->event;

        $uitPasEvent = $this->getUitPasEventLegacy($event, $cardNumber);
        $priceClass = $this->getApplicableUitPASPriceLegacy($order->ticketCategory, $uitPasEvent);

        if ($priceClass === null) {
            return;
        }

        $tariff = floatval($priceClass->tariff);

        $ticketSaleId = $this->registerOnlineSaleLegacy($event, $priceClass, $cardNumber);
        if ($ticketSaleId) {
            $order->setUiTPASTariff($tariff, $ticketSaleId);
        }
    }

    /**
     * @param Order $order
     * @throws InvalidCardException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerOrderCancelLegacy(Order $order)
    {
        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient(null, 'uitid');
        if (!$client) {
            throw new InvalidCardException('De UitPAS dienst is niet correct ingesteld voor dit account. Contacteer een administrator.');
        }

        try {
            $url = 'uitpas/cultureevent/cancelonline/' . $order->uitpas_sale_id;
            $response = $client->post($url);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorLegacy($e->getResponse());
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param TicketCategory $ticketCategory
     * @return bool
     */
    protected function hasApplicableUitPasPriceLegacy(TicketCategory $ticketCategory)
    {
        $uitPasEvent = $this->getUitPasEventLegacy($ticketCategory->event);

        try {
            $priceClass = $this->getApplicableUitPASPriceLegacy($ticketCategory, $uitPasEvent);
            if ($priceClass) {
                return true;
            } else {
                return false;
            }
        } catch (PriceClassNotFound $e) {
            return false;
        }
    }

    /**
     * @param Event $event
     * @param $uitpas
     * @return bool
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function uitPASCheckinLegacy(Event $event, $uitpas)
    {
        $organisation = $event->organisation;
        if (!$organisation->uitdb_identifier) {
            throw new UitPASException('Organisation is not authenticated.');
        }

        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        try {
            $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient($organisation, 'uitid');
            $response = $client->post(
                'uitpas/passholder/checkin',
                [
                    'form_params' => [
                        'cdbid' => $event->getUitDBId(),
                        'uitpasNumber' => $uitpas
                    ]
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorLegacy($e->getResponse());
            } else {
                throw $e;
            }
        }

        return true;
    }

    /**
     * @param Event $event
     * @param null $cardNumber
     * @return \SimpleXMLElement
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUitPasEventLegacy(Event $event, $cardNumber = null)
    {
        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient(null, 'uitid');
        if (!$client) {
            throw new InvalidCardException('De UitPAS dienst is niet correct ingesteld voor dit account. Contacteer een administrator.');
        }

        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        try {
            $query = [
                'cdbid' => $event->getUitDBId(),
                'max' => 1,
                'start' => 0
            ];

            if ($cardNumber) {
                $query['uitpasNumber'] = $cardNumber;
            }

            $response = $client->get(
                'uitpas/cultureevent/search',
                [
                    'query' => $query
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorLegacy($e->getResponse());
            } else {
                throw $e;
            }
        }

        $simpleXml = new \SimpleXMLElement((string)$response->getBody());
        if ($simpleXml->total < 1) {
            throw InvalidEventException::make();
        }

        if ($simpleXml->event->count() < 1) {
            throw InvalidEventException::make();
        }

        return $simpleXml->event[0];
    }

    /**
     * @param Event $event
     * @param \SimpleXMLElement $priceCategory
     * @param $cardNumber
     * @return \SimpleXMLElement
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerOnlineSaleLegacy(Event $event, \SimpleXMLElement $priceCategory, $cardNumber)
    {
        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient(null, 'uitid');
        if (!$client) {
            throw new InvalidCardException('De UitPAS dienst is niet correct ingesteld voor dit account. Contacteer een administrator.');
        }

        if (!$event->getUitDBId()) {
            throw InvalidEventException::make();
        }

        try {
            $url = 'uitpas/cultureevent/' . $event->getUitDBId() . '/buyonline/' . $cardNumber;
            $response = $client->post(
                $url,
                [
                    'form_params' => [
                        'priceClass' => $priceCategory->name
                    ]
                ]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiErrorLegacy($e->getResponse());
            } else {
                throw $e;
            }
        }

        $simpleXml = new \SimpleXMLElement((string)$response->getBody());
        return $simpleXml->id;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws InvalidCardException
     * @throws UitPASException
     */
    protected function handleApiErrorLegacy($response)
    {
        $xml = (string)$response->getBody();

        try {
            $simpleXml = new \SimpleXMLElement($xml);
        } catch (\Exception $e) {
            throw new UitPASException('Unknown UitPAS exception: ' . $xml);
        }

        $code = $simpleXml->code;
        $message = $simpleXml->message;

        switch ($code) {
            case 'PARSE_INVALID_UITPASNUMBER':
                throw new InvalidCardException('Je UitPAS kaartnummer kon niet worden herkend: ' . $message);

            default:
                throw new UitPASException($simpleXml->code . ' ' . $simpleXml->message);
        }
    }

    /**
     * Match our own ticket categories with the ticketPrices provided by UiTPAS (legacy).
     * @param TicketCategory $ticketCategory
     * @param \SimpleXMLElement $event
     * @return \SimpleXMLElement|null
     * @throws PriceClassNotFound
     */
    protected function getApplicableUitPASPriceLegacy(TicketCategory $ticketCategory, \SimpleXMLElement $event)
    {
        if (
            !$event->ticketSales ||
            !$event->ticketSales->ticketSale ||
            !$event->ticketSales->ticketSale->priceClasses
        ) {
            throw PriceClassNotFound::make($ticketCategory);
        }

        $priceClasses = $event->ticketSales->ticketSale->priceClasses->priceClass;

        // No discount possible?
        $buyConstraint = (string)$event->ticketSales->ticketSale->buyConstraintReason;
        switch ($buyConstraint) {
            case null: // no error? No problem!
                break;

            case 'INVALID_CARD_STATUS': // no discount? No problem. (but no discount either)
                return null;

            case 'MAXIMUM_REACHED':
                throw UitPASAlreadyUsed::make($ticketCategory);

            default:
                throw UiTPASGenericCardError::make($ticketCategory, $buyConstraint);
        }

        if ($buyConstraint === 'INVALID_CARD_STATUS') {
            return null;
        }

        // find based on name
        for ($i = 0; $i < $priceClasses->count(); $i ++) {
            $priceClass = $priceClasses[$i];
            if ($priceClass->name === $ticketCategory->name) {
                return $priceClass;
            }
        }

        // find based on price
        $price = floatval($ticketCategory->price);
        for ($i = 0; $i < $priceClasses->count(); $i ++) {
            $priceClass = $priceClasses[$i];
            $priceClassPrice = floatval($priceClass->price);
            if ($priceClassPrice === $price) {
                return $priceClass;
            }
        }

        throw PriceClassNotFound::make($ticketCategory);
    }
}
