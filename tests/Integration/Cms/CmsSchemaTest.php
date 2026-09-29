<?php

namespace Tests\Integration\Cms;

use App\Models\CmsRedirect;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

class CmsSchemaTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    public function testChildPathIsBuiltFromTheParentAndFollowsARename()
    {
        $organisation = $this->createOrganisation();
        $parent = $this->createPage($organisation, 'over-ons');
        $child = $this->createPage($organisation, 'over-ons/in-de-pers');

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('over-ons/in-de-pers', $child->translation('nl')->path);

        $grandChild = $this->createPage($organisation, 'over-ons/in-de-pers/2019');
        $this->assertEquals('over-ons/in-de-pers/2019', $grandChild->translation('nl')->path);

        $translation = $parent->translation('nl');
        $translation->slug = 'about';
        $translation->save();

        $this->assertEquals('about', $translation->fresh()->path);
        $this->assertEquals('about/in-de-pers', $child->fresh()->translation('nl')->path);
        $this->assertEquals('about/in-de-pers/2019', $grandChild->fresh()->translation('nl')->path);
    }

    public function testMovingAPageRebuildsItsPaths()
    {
        $organisation = $this->createOrganisation();
        $a = $this->createPage($organisation, 'a');
        $this->createPage($organisation, 'b');
        $child = $this->createPage($organisation, 'a/child');
        $this->createPage($organisation, 'a/child/leaf');

        $child->parent()->associate(Page::whereHas('translations', function ($q) {
            $q->where('path', '=', 'b');
        })->first());
        $child->save();

        $this->assertEquals('b/child', $child->fresh()->translation('nl')->path);
        $this->assertEquals(
            ['b/child/leaf'],
            $child->fresh()->children->map(fn (Page $p) => $p->translation('nl')->path)->all()
        );
        $this->assertCount(0, $a->fresh()->children);
    }

    public function testTranslationCopiesTheOrganisationAndStampsPublishedAt()
    {
        $organisation = $this->createOrganisation();
        $page = $this->createPage($organisation, 'draft', [], 'nl', false);

        $translation = $page->translation('nl');
        $this->assertEquals($organisation->id, $translation->organisation_id);
        $this->assertNull($translation->published_at);

        $translation->is_published = true;
        $translation->save();
        $this->assertNotNull($translation->fresh()->published_at);
    }

    public function testPathIsUniquePerOrganisationAndLocale()
    {
        $organisation = $this->createOrganisation();
        $other = $this->createOrganisation();

        $this->createPage($organisation, 'over-ons');

        // Same path, other organisation: fine.
        $this->createPage($other, 'over-ons');

        // Same path, other locale: fine.
        $this->createPage($organisation, 'over-ons', [], 'en');

        $this->expectException(QueryException::class);
        $this->createPage($organisation, 'over-ons');
    }

    public function testPostTranslationUrlUsesTheBrusselsDate()
    {
        $organisation = $this->createOrganisation();

        $post = new Post();
        $post->organisation()->associate($organisation);
        // Just after midnight in Brussels: still 13 March in UTC.
        $post->published_at = Carbon::parse('2019-03-14 00:30:00', 'Europe/Brussels');
        $post->save();

        $translation = new PostTranslation();
        $translation->post()->associate($post);
        $translation->locale = 'nl';
        $translation->slug = 'onze-eerste-quiz';
        $translation->title = 'Onze eerste quiz';
        $translation->author = 'Thijs';
        $translation->body = '<p>Hallo</p>';
        $translation->is_published = true;
        $translation->save();

        $this->assertEquals($organisation->id, $translation->organisation_id);
        $this->assertStringEndsWith('/2019/03/14/onze-eerste-quiz', $translation->fresh()->getUrl());
        $this->assertSame($translation->id, $post->translation('nl')->id);
        $this->assertCount(1, Post::published()->get());

        $translation->locale = 'en';
        $translation->save();
        $this->assertStringEndsWith('/en/2019/03/14/onze-eerste-quiz', $translation->fresh()->getUrl());
    }

    public function testRedirectsAndNewColumnsExist()
    {
        $organisation = $this->createOrganisation();

        $redirect = new CmsRedirect();
        $redirect->organisation()->associate($organisation);
        $redirect->from_path = 'category/nieuws';
        $redirect->to_url = '/blog';
        $redirect->save();

        $this->assertEquals(301, $redirect->fresh()->status_code);
        $this->assertCount(1, $organisation->cmsRedirects);

        $this->assertTrue(Schema::hasColumn('organisations', 'home_page_id'));
        $this->assertTrue(Schema::hasColumn('organisation_domains', 'is_canonical'));
        $this->assertTrue(Schema::hasColumn('assets', 'organisation_id'));
    }
}
