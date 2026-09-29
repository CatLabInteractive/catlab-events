<?php

namespace Tests\Unit\Cms;

use App\Models\Page;
use App\Models\PageTranslation;
use Tests\TestCase;

/**
 * PageTranslation::rebuildPath() on in-memory models (no database).
 */
class PagePathTest extends TestCase
{
    private function page(?Page $parent, array $translations): Page
    {
        $page = new Page();
        $page->setRelation('parent', $parent);
        $page->setRelation('translations', collect());

        foreach ($translations as $locale => $slug) {
            $translation = new PageTranslation();
            $translation->locale = $locale;
            $translation->slug = $slug;
            $translation->setRelation('page', $page);
            $translation->rebuildPath();
            $page->translations->push($translation);
        }

        return $page;
    }

    public function testTopLevelPathIsTheSlug()
    {
        $page = $this->page(null, [ 'nl' => 'over-ons' ]);
        $this->assertEquals('over-ons', $page->translation('nl')->path);
    }

    public function testChildPathIncludesTheParentPathInTheSameLocale()
    {
        $parent = $this->page(null, [ 'nl' => 'over-ons', 'en' => 'about-us' ]);
        $child = $this->page($parent, [ 'nl' => 'in-de-pers', 'en' => 'press' ]);
        $leaf = $this->page($child, [ 'nl' => '2019' ]);

        $this->assertEquals('over-ons/in-de-pers', $child->translation('nl')->path);
        $this->assertEquals('about-us/press', $child->translation('en')->path);
        $this->assertEquals('over-ons/in-de-pers/2019', $leaf->translation('nl')->path);
    }

    public function testMissingTranslationReturnsNull()
    {
        $page = $this->page(null, [ 'nl' => 'over-ons' ]);
        $this->assertNull($page->translation('fr'));
    }

    public function testUrlIsLocalePrefixedExceptForTheDefaultLocale()
    {
        $page = $this->page(null, [ 'nl' => 'over-ons', 'en' => 'about-us' ]);

        $this->assertEquals(url('/over-ons'), $page->translation('nl')->getUrl());
        $this->assertEquals(url('/en/about-us'), $page->translation('en')->getUrl());
    }
}
