<?php

namespace App\UitDB;

use App\Models\Event;
use App\Models\Order;
use App\UitDB\Exceptions\InvalidCardException;
use App\UitDB\Exceptions\InvalidEventException;
use App\UitDB\Exceptions\UitPASException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

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
     * @param string $cardNumber
     * @throws InvalidCardException
     * @throws InvalidEventException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function registerTicketSale(Order $order, $cardNumber)
    {
        /** @var Event $event */
        $event = $order->event;

        $uitPasEvent = $this->getUitPasEvent($event, $cardNumber);
        //dd($uitPasEvent->ticketSales->priceClasses);

        throw new InvalidCardException('De UitPAS dienst is tijdelijk niet bereikbaar. ' .
            'Contacteer hallo@quizfabriek.be om manueel in te schrijven.');

    }

    /**
     * @param Order $order
     */
    public function registerOrderCancel(Order $order)
    {

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
                'cdbid' => 'e2402c1a-006c-46fb-801c-a9a92fb1f64d' // @TODO temporary using a different event for testing
                // 'cdbid' => $event->getUitDBId()
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
                throw new UitPASException($simpleXml->code);
        }
    }
}
