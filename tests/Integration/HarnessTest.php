<?php

namespace Tests\Integration;

class HarnessTest extends IntegrationTestCase
{
    public function testRunsAgainstDedicatedTestDatabase()
    {
        $this->assertEquals('catlab_events_test', \DB::connection()->getDatabaseName());
    }

    public function testStatusEndpointResponds()
    {
        $this->get('/status')->assertStatus(200);
    }
}
