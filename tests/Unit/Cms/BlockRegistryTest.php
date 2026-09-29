<?php

namespace Tests\Unit\Cms;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockType;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    public function testAllV1BlockTypesAreRegistered()
    {
        $this->assertEqualsCanonicalizing(
            [ 'hero', 'rich_text', 'text_image', 'cards', 'logo_grid', 'reviews', 'cta', 'video', 'upcoming_events', 'latest_posts', 'faq' ],
            array_keys(config('cms.blocks'))
        );
    }

    public function testEveryConfiguredTypeResolvesUnderItsOwnKey()
    {
        $registry = $this->app->make(BlockRegistry::class);
        $this->assertSame($registry, $this->app->make(BlockRegistry::class));

        foreach (config('cms.blocks') as $key => $class) {
            $type = $registry->get($key);

            $this->assertInstanceOf(BlockType::class, $type, $key);
            $this->assertInstanceOf($class, $type);
            $this->assertSame($key, $type->type());
            $this->assertNotEmpty($type->label());
            $this->assertIsArray($type->rules());
        }

        $this->assertCount(count(config('cms.blocks')), $registry->all());
        $this->assertNull($registry->get('does_not_exist'));
    }

    public function testEveryTypeHasAView()
    {
        foreach ($this->app->make(BlockRegistry::class)->all() as $key => $type) {
            $this->assertTrue(View::exists($type->view()), $type->view());
            $this->assertTrue(View::exists($type->formView()), $type->formView());
        }
    }

    public function testHtmlAndAssetFieldsAreCoveredByRules()
    {
        foreach ($this->app->make(BlockRegistry::class)->all() as $key => $type) {
            foreach (array_merge($type->htmlFields(), $type->assetFields()) as $field) {
                $this->assertArrayHasKey($field, $type->rules(), $key . '.' . $field);
            }
        }
    }
}
