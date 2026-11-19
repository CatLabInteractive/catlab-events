<?php

namespace Tests\Unit;

use App\Models\Event;
use Tests\TestCase;

/**
 * The "Meer informatie" link must not point at the old WordPress event pages,
 * which 404 now that quizfabriek.be is served by this app.
 */
class EventMoreInfoUrlTest extends TestCase
{
    private function event(?string $url): Event
    {
        config([ 'app.url' => 'https://www.quizfabriek.be' ]);
        $event = new Event();
        $event->id = 54;
        $event->event_url = $url;
        return $event;
    }

    public function testEmptyUrlHasNoLink()
    {
        $this->assertNull($this->event(null)->getMoreInfoUrl());
        $this->assertNull($this->event('  ')->getMoreInfoUrl());
    }

    public function testOldWordpressEventPageIsDropped()
    {
        $this->assertNull($this->event('https://www.quizfabriek.be/events/reis-rond-de-wereld-in-80-vragen/')->getMoreInfoUrl());
        $this->assertNull($this->event('https://quizfabriek.be/events/reis-rond-de-wereld-in-80-vragen')->getMoreInfoUrl());
    }

    public function testLinkToOwnEventPageIsDropped()
    {
        $this->assertNull($this->event('https://www.quizfabriek.be/events/54/reis-rond-de-wereld-in-80-vragen')->getMoreInfoUrl());
        $this->assertNull($this->event('https://www.quizfabriek.be/e/54')->getMoreInfoUrl());
    }

    public function testOtherLinksAreKept()
    {
        $this->assertSame('https://www.cultuurcentrum.be/quiz', $this->event('https://www.cultuurcentrum.be/quiz')->getMoreInfoUrl());
        $this->assertSame('https://www.quizfabriek.be/events/86/quiz-in-cafe', $this->event('https://www.quizfabriek.be/events/86/quiz-in-cafe')->getMoreInfoUrl());
        $this->assertSame('https://www.quizfabriek.be/quizzen-op-maat', $this->event('https://www.quizfabriek.be/quizzen-op-maat')->getMoreInfoUrl());
        $this->assertSame('https://elsewhere.be/events/some-slug/', $this->event('https://elsewhere.be/events/some-slug/')->getMoreInfoUrl());
    }
}
