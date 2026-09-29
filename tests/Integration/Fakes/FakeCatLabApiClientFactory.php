<?php

namespace Tests\Integration\Fakes;

use App\Models\User;
use App\Services\CatLabApiClientFactory;
use CatLab\Accounts\Client\ApiClient;

class FakeCatLabApiClientFactory extends CatLabApiClientFactory
{
    private $client;

    public function __construct(FakeCatLabApiClient $client)
    {
        $this->client = $client;
    }

    public function forUser(?User $user = null): ApiClient
    {
        // One shared fake; remember who the next call is made as.
        $this->client->currentUser = $user;
        return $this->client;
    }
}
