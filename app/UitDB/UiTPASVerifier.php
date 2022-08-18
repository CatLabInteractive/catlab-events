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
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

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

        $uitPasEvent = $this->getUitPasEvent($event, $cardNumber);
        $priceClass = $this->getApplicableUitPASPrice($ticketCategory, $uitPasEvent);

        // no discount possible? Continue without.
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
    public function registerTicketSale(Order $order, $cardNumber)
    {
        /** @var Event $event */
        $event = $order->event;

        $uitPasEvent = $this->getUitPasEvent($event, $cardNumber);
        $priceClass = $this->getApplicableUitPASPrice($order->ticketCategory, $uitPasEvent);

        // no discount possible? Continue without.
        if ($priceClass === null) {
            return;
        }

        $tariff = floatval($priceClass->tariff);

        $ticketSaleId = $this->registerOnlineSale($event, $priceClass, $cardNumber);
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
    public function registerOrderCancel(Order $order)
    {
        if (!$order->uitpas_sale_id) {
            return;
        }

        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient(null, 'uitid');
        if (!$client) {
            throw new InvalidCardException('De UitPAS dienst is niet correct ingesteld voor dit account. Contacteer een administrator.');
        }

        try {
            $url = 'uitpas/cultureevent/cancelonline/' . $order->uitpas_sale_id;
            $response = $client->post($url);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $this->handleApiError($e->getResponse());
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param Event $event
     * @param null $cardNumber
     * @return SimpleXMLElement
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUitPasEvent(Event $event, $cardNumber = null)
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
                'cdbid' => $event->getUitDBId()
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
                $this->handleApiError($e->getResponse());
            } else {
                throw $e;
            }
        }

        $simpleXml = new SimpleXMLElement((string)$response->getBody());
        if ($simpleXml->total < 1) {
            throw InvalidEventException::make();
        }

        return $simpleXml->event[0];
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
        $uitPasEvent = $this->getUitPasEvent($ticketCategory->event);

        try {
            $priceClass = $this->getApplicableUitPASPrice($ticketCategory, $uitPasEvent);
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
     * @param SimpleXMLElement $priceCategory
     * @param $cardNumber
     * @return SimpleXMLElement
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function registerOnlineSale(Event $event, SimpleXMLElement $priceCategory, $cardNumber)
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
                $this->handleApiError($e->getResponse());
            } else {
                throw $e;
            }
        }

        $simpleXml = new SimpleXMLElement((string)$response->getBody());
        return $simpleXml->id;
    }

    /**
     * @param ResponseInterface $response
     * @throws InvalidCardException
     * @throws UitPASException
     */
    protected function handleApiError(ResponseInterface $response)
    {
        $xml = (string)$response->getBody();

        try {
            $simpleXml = new SimpleXMLElement($xml);
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
     * Match our own ticket categories with the ticketPrices provided by UiTPAS.
     * First it checks names for an exact match, then it checks prices for an exact match.
     * @param TicketCategory $ticketCategory
     * @param SimpleXMLElement $event
     * @return SimpleXMLElement|null
     * @throws PriceClassNotFound
     */
    protected function getApplicableUitPASPrice(TicketCategory $ticketCategory, SimpleXMLElement $event)
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

    /**
     * @param $event
     * @return bool
     */
    public function canCheckIn(Event $event)
    {
        $organisation = $event->organisation;
        if (!$organisation->uitdb_identifier) {
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
                $this->handleApiError($e->getResponse());
            } else {
                throw $e;
            }
        }

        return true;
    }
}
