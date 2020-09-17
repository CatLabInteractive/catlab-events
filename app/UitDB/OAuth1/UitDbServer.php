<?php

namespace App\UitDB\OAuth1;

use League\OAuth1\Client\Credentials\TokenCredentials;
use League\OAuth1\Client\Server\Server;
use League\OAuth1\Client\Server\User;
use League\OAuth1\Client\Signature\SignatureInterface;

/**
 * Class UitDbServer
 * @package App\UitDB\OAuth1
 */
class UitDbServer extends Server
{
    private $baseUrl = null;

    /**
     * UitDbServer constructor.
     * @param array $clientCredentials
     * @param SignatureInterface|null $signature
     */
    public function __construct(array $clientCredentials, SignatureInterface $signature = null)
    {
        parent::__construct($clientCredentials, $signature);

        $this->baseUrl = $clientCredentials['base_url'];
    }

    public function urlTemporaryCredentials()
    {
        return $this->baseUrl . 'requestToken';
    }

    public function urlAuthorization()
    {
        return $this->baseUrl . 'auth/authorize';
    }

    public function urlTokenCredentials()
    {
        return $this->baseUrl . 'accessToken';
    }

    public function urlUserDetails()
    {
        return $this->baseUrl . 'user';
    }

    public function userDetails($data, TokenCredentials $tokenCredentials)
    {
        $user = new User();

        return $user;
        // TODO: Implement userDetails() method.
    }

    public function userUid($data, TokenCredentials $tokenCredentials)
    {
        // TODO: Implement userUid() method.
    }

    public function userEmail($data, TokenCredentials $tokenCredentials)
    {
        // TODO: Implement userEmail() method.
    }

    public function userScreenName($data, TokenCredentials $tokenCredentials)
    {
        // TODO: Implement userScreenName() method.
    }
}
