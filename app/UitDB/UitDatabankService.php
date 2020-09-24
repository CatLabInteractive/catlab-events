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

use App\Models\Organisation;
use App\UitDB\Contracts\UitDBFacade;
use App\UitDB\Contracts\UitDBService;
use App\UitDB\OAuth1\UitDbServer;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use SimpleXMLElement;

/**
 * Class UitDatabank
 * @package App\UitDB
 */
class UitDatabankService implements UitDBService
{
    /**
     * @var string
     */
    private $entryApiKey;

    /**
     * @var string
     */
    private $env;

    /**
     * @var string
     */
    private $jwt;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var \Guzzle\Http\Client
     */
    private $guzzle;

    /**
     * @var string
     */
    private $oauthConsumer;

    /**
     * @var string
     */
    private $oauthSecret;

    /**
     * @var Organisation
     */
    private $organisation;

    /**
     * @return UitDatabankService|null
     */
    public static function fromConfig()
    {
        return new self(
            config('services.uitdb.env'),
            config('services.uitdb.entry_api_key'),
            config('services.uitdb.oauth_consumer'),
            config('services.uitdb.oauth_secret')
        );
    }

    /**
     * @param Organisation $organisation
     */
    public function setOrganisation(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    /**
     * UitDatabank constructor.
     * @param $env
     * @param $key
     * @param $oauthConsumer
     * @param $oauthSecret
     */
    public function __construct(
        $env,
        $entryApiKey,
        $oauthConsumer,
        $oauthSecret
    ) {
        $this->entryApiKey = $entryApiKey;
        $this->env = $env;

        $this->oauthConsumer = $oauthConsumer;
        $this->oauthSecret = $oauthSecret;

        $this->guzzle = new \GuzzleHttp\Client();
    }

    /**
     * @return string
     */
    public function getEntryApiKey()
    {
        return $this->entryApiKey;
    }

    /**
     * @param $redirectUrl
     * @return string
     */
    public function getConnectUrl($redirectUrl)
    {
        $environment = $this->getEnvironment();
        return $environment['jwt'] . '/connect?apiKey=' . urlencode($this->entryApiKey) . '&destination=' . urlencode($redirectUrl);
    }

    /**
     * @return string[]
     */
    public function getEnvironment()
    {
        switch ($this->env) {
            case 'test':
                return [
                    'uitid' => 'https://test.uitid.be/uitid/rest/',


                    'io' => 'https://io-test.uitdatabank.be',

                    'ui' => 'https://test.uitdatabank.be',
                    'jwt' => 'https://jwt-test.uitdatabank.be',
                    'legacy' => 'https://test.uitid.be/uitid/rest/'
                ];

            default:
                return [
                    'io' => 'https://io.uitdatabank.be',
                    'rest' => 'https://www.uitid.be/uitid/rest/',
                    'ui' => 'https://www.uitdatabank.be',
                    'jwt' => 'https://jwt.uitdatabank.be'
                ];
        }
    }

    /**
     * @param string $jwt
     * @param string $refreshTokens
     */
    public function setConnectAuthentication($jwt, $refreshToken = null)
    {
        $this->jwt = $jwt;
        $this->refreshToken = $refreshToken;
    }

    /**
     * @return SimpleXMLElement|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser()
    {
        if (!$this->organisation) {
            return null;
        }

        $oauthClient = $this->getOauth1ConsumerGuzzleClient($this->organisation);
        $xml = (string)$oauthClient->get('user')->getBody();

        $simpleXml = new SimpleXMLElement($xml);

        $namespaces = $simpleXml->getNamespaces();
        return $simpleXml->children($namespaces['foaf']);
    }

    /**
     * @param $method
     * @param $path
     * @return \Psr\Http\Message\StreamInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function authenticatedRequest($method, $path)
    {
        $url = $this->getEnvironment()['io'] . $path;

        $headers = [
            'Authorization' => 'Bearer ' . $this->jwt,
            'X-Api-Key' => $this->entryApiKey
        ];

        $response = $this->guzzle->request($method, $url, [
            'headers' => $headers
        ]);

        return $response->getBody();
    }

    /**
     * @return UitPASVerifier
     */
    public function getUitPasService(): ?UitPASVerifier
    {
        if (!$this->oauthConsumer) {
            return null;
        }
        return new UitPASVerifier($this);
    }

    /**
     * @param Organisation|null $organisation
     * @param string $namespace
     * @return Client
     */
    public function getOauth1ConsumerGuzzleClient(
        Organisation $organisation = null,
        $namespace = 'uitid'
    ) {
        $url = $this->getEnvironment();

        $stack = HandlerStack::create();
        $middleware = new Oauth1([
            'consumer_key'    => $this->oauthConsumer,
            'consumer_secret' => $this->oauthSecret,
            'token'           => $organisation ? $organisation->uitdb_identifier : null,
            'token_secret'    => $organisation ? $organisation->uitdb_secret : null
        ]);
        $stack->push($middleware);

        $client = new \GuzzleHttp\Client([
            'base_uri' => $url[$namespace],
            'handler' => $stack,
            'auth' => 'oauth'
        ]);

        return $client;
    }

    /**
     * @param Organisation|null $organisation
     * @param string $namespace
     * @return Client
     */
    public function getEntrySearchGuzzleClient(
        Organisation $organisation = null,
        $namespace = 'uitid'
    ) {
        $url = $this->getEnvironment();

        $headers = [
            'X-Api-Key' => $this->getEntryApiKey()
        ];

        $client = new \GuzzleHttp\Client([
            'base_uri' => $url[$namespace],
            'headers' => $headers
        ]);

        return $client;
    }

    /**
     * @return UitDbServer
     */
    public function getOAuth1Authenticator($redirectUrl)
    {
        $server = new UitDbServer([
            'base_url' => $this->getEnvironment()['uitid'],
            'identifier' => $this->oauthConsumer,
            'secret' => $this->oauthSecret,
            'callback_uri' => $redirectUrl
        ]);

        return $server;
    }
}
