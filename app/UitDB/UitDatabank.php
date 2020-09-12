<?php


namespace App\UitDB;

use GuzzleHttp\Client;

/**
 * Class UitDatabank
 * @package App\UitDB
 */
class UitDatabank
{
    /**
     * @var string
     */
    private $key;

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
     * @param $key
     * @param $env
     */
    public function __construct(
        $key,
        $env
    ) {
        $this->key = $key;
        $this->env = $env;

        $this->guzzle = new \GuzzleHttp\Client();
    }

    /**
     * @return string
     */
    public function getApplicationKey()
    {
        return $this->key;
    }

    /**
     * @param $redirectUrl
     * @return string
     */
    public function getConnectUrl($redirectUrl)
    {
        $environment = $this->getEnvironment();
        return $environment['jwt'] . '/connect?apiKey=' . urlencode($this->key) . '&destination=' . urlencode($redirectUrl);
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
                    'uitpas' => 'https://uitpas-test.uitdatabank.be',
                    'ui' => 'https://test.uitdatabank.be',
                    'jwt' => 'https://jwt-test.uitdatabank.be'
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
    public function setAuthentication($jwt, $refreshToken = null)
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
            'X-Api-Key' => $this->key
        ];

        $response = $this->guzzle->request($method, $url, [
            'headers' => $headers
        ]);

        return $response->getBody();
    }
}
