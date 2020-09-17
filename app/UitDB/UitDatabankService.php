<?php


namespace App\UitDB;

use App\UitDB\Contracts\UitDBFacade;
use App\UitDB\Contracts\UitDBService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * Class UitDatabank
 * @package App\UitDB
 */
class UitDatabankService implements UitDBService
{
    /**
     * @var string
     */
    private $connectKey;

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
     * @return UitDatabankService|null
     */
    public static function fromConfig()
    {
        return new self(
            config('services.uitdb.env'),
            config('services.uitdb.connect_key'),
            config('services.uitdb.oauth_consumer'),
            config('services.uitdb.oauth_secret')
        );
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
        $key,
        $oauthConsumer,
        $oauthSecret
    ) {
        $this->connectKey = $key;
        $this->env = $env;

        $this->oauthConsumer = $oauthConsumer;
        $this->oauthSecret = $oauthSecret;

        $this->guzzle = new \GuzzleHttp\Client();
    }

    /**
     * @return string
     */
    public function getApplicationKey()
    {
        return $this->connectKey;
    }

    /**
     * @param $redirectUrl
     * @return string
     */
    public function getConnectUrl($redirectUrl)
    {
        $environment = $this->getEnvironment();
        return $environment['jwt'] . '/connect?apiKey=' . urlencode($this->connectKey) . '&destination=' . urlencode($redirectUrl);
    }

    /**
     * @return string[]
     */
    public function getEnvironment()
    {
        switch ($this->env) {
            case 'test':
                return [
                    'io' => 'https://io-test.uitdatabank.be',
                    'uitpas' => 'https://test.uitid.be/uitid/rest/',
                    'ui' => 'https://test.uitdatabank.be',
                    'jwt' => 'https://jwt-test.uitdatabank.be',
                    'legacy' => 'https://test.uitid.be/uitid/rest/'
                ];

            default:
                return [
                    'io' => 'https://io.uitdatabank.be',
                    'uitpas' => 'https://uitpas.uitdatabank.be',
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
     * @return \Psr\Http\Message\StreamInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser()
    {
        return $this->authenticatedRequest('GET', '/user');
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
            'X-Api-Key' => $this->connectKey
        ];

        $response = $this->guzzle->request($method, $url, [
            'headers' => $headers
        ]);

        return $response->getBody();
    }

    /**
     * @return UitPASVerifier
     */
    public function getUitPasService(): UitPASVerifier
    {
        return new UitPASVerifier($this);
    }

    /**
     * @param string $namespace
     * @return Client
     */
    public function getOauth1ConsumerGuzzleClient($namespace = 'ui')
    {
        $url = $this->getEnvironment();

        $stack = HandlerStack::create();
        $middleware = new Oauth1([
            'consumer_key'    => $this->oauthConsumer,
            'consumer_secret' => $this->oauthSecret,
            'token'           => '',
            'token_secret'    => ''
        ]);
        $stack->push($middleware);

        $client = new \GuzzleHttp\Client([
            'base_uri' => $url[$namespace],
            'handler' => $stack,
            'auth' => 'oauth'
        ]);

        return $client;
    }
}
