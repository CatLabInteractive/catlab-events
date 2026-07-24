<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;
}
