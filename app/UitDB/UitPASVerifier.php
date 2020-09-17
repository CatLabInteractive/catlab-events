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
use App\UitDB\Exceptions\InvalidCardException;
use GuzzleHttp\Exception\RequestException;

/**
 * Class UitPASVerifier
 * @package App\Tools
 */
class UitPASVerifier
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
     * @param Order $order
     * @param $cardNumber
     * @throws InvalidCardException
     */
    public function registerTicketSale(Order $order, $cardNumber)
    {
        /** @var Event $event */
        $event = $order->event;

        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient($order->organisation);
        if (!$client) {
            throw new InvalidCardException('De UitPAS dienst is niet correct ingesteld voor dit account. Contacteer een administrator.');
        }

        try {
            $response = $client->get('uitpas/cultureevent/search?cdbid=' . $event->getUitDBId() . '&uitpasNumber=' . $cardNumber);
        } catch (RequestException $e) {
            throw new InvalidCardException('Je UitPAS kaartnummer kon niet worden herkend. Geef het nummer opnieuw in.');
        }

        //dd((string)$response->getBody());

        throw new InvalidCardException('De UitPAS dienst is tijdelijk niet bereikbaar. Contacteer hallo@quizfabriek.be om manueel in te schrijven.');
        die((string)$response->getBody());
    }

    /**
     * @param Order $order
     */
    public function registerOrderCancel(Order $order)
    {

    }
}
