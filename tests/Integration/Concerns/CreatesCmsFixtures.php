<?php

namespace Tests\Integration\Concerns;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use App\Models\Page;
use App\Models\PageTranslation;

/**
 * Builds CMS rows (pages, translations, domains) for the integration tests.
 */
trait CreatesCmsFixtures
{
    /**
     * Create a page with one translation at $path. The parent page is looked
     * up by the translation path of everything before the last segment, so
     * create parents first ('over-ons', then 'over-ons/in-de-pers').
     */
    protected function createPage(
        Organisation $organisation,
        string $path,
        array $blocks = [],
        string $locale = 'nl',
        bool $published = true
    ): Page {
        $segments = explode('/', trim($path, '/'));
        $slug = array_pop($segments);

        $parent = null;
        if (count($segments) > 0) {
            $parentTranslation = PageTranslation::where('organisation_id', '=', $organisation->id)
                ->where('locale', '=', $locale)
                ->where('path', '=', implode('/', $segments))
                ->firstOrFail();
            $parent = $parentTranslation->page;
        }

        $page = new Page();
        $page->organisation()->associate($organisation);
        if ($parent) {
            $page->parent()->associate($parent);
        }
        $page->save();

        $this->createPageTranslation($page, $locale, $slug, $blocks, $published);

        return $page;
    }

    /**
     * Add a translation to an existing page.
     */
    protected function createPageTranslation(
        Page $page,
        string $locale,
        string $slug,
        array $blocks = [],
        bool $published = true,
        ?string $title = null
    ): PageTranslation {
        $translation = new PageTranslation();
        $translation->page()->associate($page);
        $translation->locale = $locale;
        $translation->slug = $slug;
        $translation->title = $title ?? ('Page ' . $slug . ' (' . $locale . ')');
        $translation->blocks = $blocks;
        $translation->is_published = $published;
        $translation->save();

        $page->unsetRelation('translations');

        return $translation;
    }

    protected function createOrganisationDomain(Organisation $organisation, string $host): OrganisationDomain
    {
        $domain = new OrganisationDomain();
        $domain->organisation()->associate($organisation);
        $domain->domain = $host;
        $domain->save();

        return $domain;
    }

    /**
     * Pretend the next request comes in on $host. Organisation resolution is
     * memoised per process from $_SERVER['HTTP_HOST'], so reset it too.
     */
    protected function actAsHost(string $host): void
    {
        $_SERVER['HTTP_HOST'] = $host;
        Organisation::resetRepresentedOrganisation();
    }
}
