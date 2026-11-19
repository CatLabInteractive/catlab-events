<?php

namespace Tests\Integration;

use App\Models\Series;
use Tests\Integration\Concerns\CreatesEventFixtures;

class OrganisationColoursTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    public function testPagesPrintThePaletteCss()
    {
        $event = $this->createEvent($this->createOrganisation());
        $this->createTicketCategory($event, 10.0);

        // The represented organisation is cached per process, so only check that the palette is printed.
        $this->get('/events/' . $event->id)
            ->assertStatus(200)
            ->assertSee('<style>:root{--kv-c0:#', false);
    }

    public function testOrganisationColoursAreStoredAndUsed()
    {
        $organisation = $this->createOrganisation();
        $organisation->theme_colours = '#e63946, #264653, #2a9d8f';
        $organisation->save();
        $organisation = $organisation->fresh();

        $css = $organisation->getColourPalette()->toCss();
        $this->assertStringContainsString('.kv-field-1{--kv-field:#264653;', $css);
        $this->assertStringNotContainsString('.kv-field-3{', $css);

        $series = new Series();
        $series->id = 7;
        $series->organisation()->associate($organisation);
        $this->assertSame('kv-field-1', $series->getColourClass());
    }

    public function testWithoutColoursTheDefaultPaletteIsUsed()
    {
        $organisation = $this->createOrganisation();

        $this->assertSame(6, $organisation->getColourPalette()->count());
        $this->assertStringContainsString('.kv-field-1{--kv-field:#5e2fff;', $organisation->getColourPalette()->toCss());
    }
}
