<?php

namespace Tests\Integration\Fakes;

use CatLab\Eukles\Client\EuklesClient;

class FakeEuklesClient extends EuklesClient
{
    public $tracked = [];

    public function __construct()
    {
        parent::__construct('https://eukles.invalid', 'test-key', 'test-secret', 'testing');
    }

    public function trackEvent(\CatLab\Eukles\Client\Models\Event $event)
    {
        $this->tracked[] = $event;

        return true;
    }

    public function trackEvents(array $events)
    {
        foreach ($events as $event) {
            $this->trackEvent($event);
        }

        return true;
    }
}
