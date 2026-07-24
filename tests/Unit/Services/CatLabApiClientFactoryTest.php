<?php

namespace Tests\Unit\Services;

use App\Services\CatLabApiClientFactory;
use CatLab\Accounts\Client\ApiClient;
use Tests\TestCase;

class CatLabApiClientFactoryTest extends TestCase
{
    public function testCreatesApiClient()
    {
        $factory = $this->app->make(CatLabApiClientFactory::class);

        $this->assertInstanceOf(ApiClient::class, $factory->forUser(null));
    }

    public function testContainerBindingCanBeSwapped()
    {
        $fake = new class extends CatLabApiClientFactory {
        };
        $this->app->instance(CatLabApiClientFactory::class, $fake);

        $this->assertSame($fake, $this->app->make(CatLabApiClientFactory::class));
    }
}
