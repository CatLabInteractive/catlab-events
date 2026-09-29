<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Services\CatLabApiClientFactory;
use CatLab\Eukles\Client\Interfaces\EuklesClient as EuklesClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Integration\Fakes\FakeCatLabApiClient;
use Tests\Integration\Fakes\FakeCatLabApiClientFactory;
use Tests\Integration\Fakes\FakeEuklesClient;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @var FakeCatLabApiClient
     */
    protected $catlabApi;

    /**
     * @var FakeEuklesClient
     */
    protected $eukles;

    protected function setUp(): void
    {
        // Organisation::getRepresentedOrganisation() is memoised per process
        // from $_SERVER['HTTP_HOST'] (and AppServiceProvider reads it while
        // booting); start every test from a clean slate.
        unset($_SERVER['HTTP_HOST']);
        Organisation::resetRepresentedOrganisation();

        parent::setUp();

        $this->catlabApi = new FakeCatLabApiClient();
        $this->app->instance(
            CatLabApiClientFactory::class,
            new FakeCatLabApiClientFactory($this->catlabApi)
        );

        $this->eukles = new FakeEuklesClient();
        $this->app->instance(EuklesClientInterface::class, $this->eukles);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST']);
        Organisation::resetRepresentedOrganisation();

        parent::tearDown();
    }
}
