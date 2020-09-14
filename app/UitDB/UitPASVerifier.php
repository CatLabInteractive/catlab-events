<?php

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

        $client = $this->uitDatabank->getOauth1ConsumerGuzzleClient('uitpas');

        try {
            $response = $client->get('uitpas/cultureevent/search?cdbid=' . $event->getUitDBId() . '&uitpasNumber=' . $cardNumber);
        } catch (RequestException $e) {
            throw new InvalidCardException('Je UitPAS kaartnummer kon niet worden herkend. Geef het nummer opnieuw in.');
        }

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
