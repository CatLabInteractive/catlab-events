<?php

namespace Tests\Unit;

use App\Tools\ColourPalette;
use PHPUnit\Framework\TestCase;

class ColourPaletteTest extends TestCase
{
    public function testEmptyDefinitionUsesTheDefaultPalette()
    {
        $palette = new ColourPalette(null);

        $this->assertSame(6, $palette->count());
        $this->assertSame('#61cc6f', $palette->getFields()[0]['field']);
        $this->assertSame('#fcc765', $palette->getFields()[0]['circle']);
    }

    public function testParsesFieldsWithAndWithoutCircleColours()
    {
        $palette = new ColourPalette("#E63946/#fff, 2a9d8f\n#abc");
        $fields = $palette->getFields();

        $this->assertCount(3, $fields);
        $this->assertSame('#e63946', $fields[0]['field']);
        $this->assertSame('#ffffff', $fields[0]['circle']);
        $this->assertSame('#2a9d8f', $fields[1]['field']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $fields[1]['circle']);
        $this->assertSame('#aabbcc', $fields[2]['field']);
    }

    public function testPicksReadableTextColour()
    {
        $this->assertSame(ColourPalette::WHITE, ColourPalette::textColourFor('#5e2fff'));
        $this->assertSame(ColourPalette::WHITE, ColourPalette::textColourFor('#4a6a9a'));
        $this->assertSame(ColourPalette::INK, ColourPalette::textColourFor('#fcc765'));
        $this->assertSame(ColourPalette::INK, ColourPalette::textColourFor('#61cc6f'));
    }

    public function testIgnoresAnythingThatIsNotAHexColour()
    {
        $palette = new ColourPalette('red, #12345, }</style><script>alert(1)</script>, #00ff00');

        $this->assertSame(1, $palette->count());
        $this->assertStringNotContainsString('<', $palette->toCss());
        $this->assertStringNotContainsString('script', $palette->toCss());
    }

    public function testOnlyInvalidColoursFallBackToTheDefault()
    {
        $this->assertSame(6, (new ColourPalette('not a colour'))->count());
    }

    public function testClassesCycleThroughThePalette()
    {
        $palette = new ColourPalette('#111111, #222222, #333333');

        $this->assertSame('kv-field-0', $palette->getClassFor(3));
        $this->assertSame('kv-field-1', $palette->getClassFor(4));
        $this->assertStringContainsString('.kv-field-2{--kv-field:#333333', $palette->toCss());
        // The ticket tiles use four colours, so short palettes are repeated.
        $this->assertStringContainsString('--kv-c3:#111111', $palette->toCss());
    }
}
