<?php

namespace Tests\Unit\Cms;

use App\Cms\BlockValidator;
use App\Cms\HtmlSanitizer;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BlockValidatorTest extends TestCase
{
    /**
     * @var BlockValidator
     */
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetInstance(HtmlSanitizer::class);
        $this->validator = $this->app->make(BlockValidator::class);
    }

    private function errors(array $blocks): array
    {
        return Validator::make([ 'blocks' => $blocks ], $this->validator->rules($blocks, 1))
            ->errors()
            ->toArray();
    }

    private function hero(array $data = []): array
    {
        return [
            'id' => 'a1b2c3',
            'type' => 'hero',
            'data' => array_merge([ 'title' => 'Welkom', 'align' => 'center' ], $data),
        ];
    }

    public function testValidBlocksPass()
    {
        $this->assertSame([], $this->errors([
            $this->hero([ 'buttons' => [ [ 'label' => 'Kalender', 'url' => '/calendar', 'style' => 'primary' ] ] ]),
            [ 'id' => '00ff00', 'type' => 'rich_text', 'data' => [ 'html' => '<p>Hallo</p>' ] ],
            [ 'id' => '00ff01', 'type' => 'upcoming_events', 'data' => [ 'limit' => 4, 'event_type' => 'package' ] ],
        ]));
    }

    public function testUnknownTypeIsRejected()
    {
        $errors = $this->errors([ [ 'id' => 'a1b2c3', 'type' => 'marquee', 'data' => [] ] ]);

        $this->assertArrayHasKey('blocks.0.type', $errors);
    }

    public function testIdMustBeSixHex()
    {
        $block = $this->hero();
        $block['id'] = 'not-an-id';

        $this->assertArrayHasKey('blocks.0.id', $this->errors([ $block ]));
    }

    public function testTypeRulesApplyAtTheRightIndex()
    {
        $button = [ 'label' => 'x', 'url' => '/x', 'style' => 'primary' ];

        $errors = $this->errors([
            [ 'id' => '00ff00', 'type' => 'rich_text', 'data' => [ 'html' => '<p>ok</p>' ] ],
            $this->hero([ 'buttons' => [ $button, $button, $button, $button ] ]),
        ]);

        $this->assertArrayHasKey('blocks.1.data.buttons', $errors);
        $this->assertArrayNotHasKey('blocks.0.data.buttons', $errors);
    }

    public function testButtonUrlsMustBeSafe()
    {
        $errors = $this->errors([
            $this->hero([ 'buttons' => [ [ 'label' => 'x', 'url' => 'javascript:alert(1)', 'style' => 'primary' ] ] ]),
        ]);

        $this->assertArrayHasKey('blocks.0.data.buttons.0.url', $errors);
    }

    public function testRequiredFieldsAreEnforced()
    {
        $errors = $this->errors([ [ 'id' => 'a1b2c3', 'type' => 'hero', 'data' => [ 'align' => 'diagonal' ] ] ]);

        $this->assertArrayHasKey('blocks.0.data.title', $errors);
        $this->assertArrayHasKey('blocks.0.data.align', $errors);
    }

    public function testVideoOnlyAcceptsYoutube()
    {
        $this->assertArrayHasKey('blocks.0.data.youtube_url', $this->errors([
            [ 'id' => 'a1b2c3', 'type' => 'video', 'data' => [ 'youtube_url' => 'https://evil.test/watch?v=abc' ] ],
        ]));

        $this->assertSame([], $this->errors([
            [ 'id' => 'a1b2c3', 'type' => 'video', 'data' => [ 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ] ],
        ]));
    }

    public function testTooManyBlocksFail()
    {
        $blocks = [];
        for ($i = 0; $i < 41; $i++) {
            $blocks[] = [ 'id' => sprintf('%06x', $i), 'type' => 'rich_text', 'data' => [ 'html' => '<p>x</p>' ] ];
        }

        $this->assertArrayHasKey('blocks', $this->errors($blocks));
        $this->assertArrayNotHasKey('blocks', $this->errors(array_slice($blocks, 0, 40)));
    }

    public function testNormaliseSanitisesHtmlFields()
    {
        $out = $this->validator->normalise([
            [ 'id' => 'a1b2c3', 'type' => 'rich_text', 'data' => [ 'html' => '<p onclick="x()">Hi</p><script>alert(1)</script>' ] ],
            [ 'id' => 'a1b2c4', 'type' => 'faq', 'data' => [ 'items' => [
                [ 'question' => 'Waarom?', 'answer' => '<h1>Daarom</h1><script>x</script>' ],
            ] ] ],
        ]);

        $this->assertSame('<p>Hi</p>', $out[0]['data']['html']);
        $this->assertSame('<h2>Daarom</h2>', $out[1]['data']['items'][0]['answer']);
        // Plain text is left alone; it is escaped when rendered.
        $this->assertSame('Waarom?', $out[1]['data']['items'][0]['question']);
    }

    public function testNormaliseDropsUnknownKeysAndBlocks()
    {
        $out = $this->validator->normalise([
            $this->hero([
                'evil' => '<script>',
                'buttons' => [ [ 'label' => 'x', 'url' => '/x', 'style' => 'primary', 'onclick' => 'y' ] ],
            ]) + [ 'extra' => 'dropped' ],
            [ 'id' => 'ffffff', 'type' => 'marquee', 'data' => [] ],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame([ 'id', 'type', 'data' ], array_keys($out[0]));
        $this->assertArrayNotHasKey('evil', $out[0]['data']);
        $this->assertSame('Welkom', $out[0]['data']['title']);
        $this->assertSame([ [ 'label' => 'x', 'url' => '/x', 'style' => 'primary' ] ], $out[0]['data']['buttons']);
    }

    public function testNormaliseCastsFormStringsToIntegersAndBooleans()
    {
        $out = $this->validator->normalise([
            [ 'id' => 'a1b2c3', 'type' => 'upcoming_events', 'data' => [
                'limit' => '6', 'event_type' => 'all', 'show_sold_out' => '0', 'title' => '12',
            ] ],
            [ 'id' => 'a1b2c4', 'type' => 'hero', 'data' => [ 'title' => 'x', 'align' => 'left', 'image_id' => null ] ],
        ]);

        $this->assertSame(6, $out[0]['data']['limit']);
        $this->assertFalse($out[0]['data']['show_sold_out']);
        // Plain strings stay strings.
        $this->assertSame('12', $out[0]['data']['title']);
        $this->assertNull($out[1]['data']['image_id']);
    }

    public function testSanitiseOnlyTouchesHtmlFields()
    {
        $blocks = [
            [ 'id' => 'a1b2c3', 'type' => 'rich_text', 'data' => [ 'html' => '<p>ok</p><script>x</script>', 'extra' => '<b>kept</b>' ] ],
            [ 'id' => 'a1b2c4', 'type' => 'marquee', 'data' => [ 'html' => '<script>unknown type, left alone</script>' ] ],
            'not a block',
        ];

        $out = $this->validator->sanitise($blocks);

        $this->assertSame('<p>ok</p>', $out[0]['data']['html']);
        $this->assertSame('<b>kept</b>', $out[0]['data']['extra']);
        $this->assertSame($blocks[1], $out[1]);
        $this->assertSame('not a block', $out[2]);
    }
}
