<?php

namespace Tests\Integration\Concerns;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use Carbon\Carbon;
use App\Models\User;
use CatLab\CentralStorage\Client\Models\Asset;

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

    /**
     * A site admin (IsAdmin middleware) who also administers $organisation
     * (organisation role 10, what the policies and admin controllers check).
     */
    protected function createOrganisationAdmin(Organisation $organisation): User
    {
        $admin = new User();
        $admin->name = 'Organisation admin';
        $admin->email = uniqid('admin', true) . '@example.com';
        $admin->password = bcrypt('secret');
        $admin->save();

        $admin->admin = true;
        $admin->save();

        $organisation->users()->attach($admin, [ 'role' => 10 ]);

        return $admin;
    }

    /**
     * An image asset row, owned by $organisation (null: a legacy asset
     * without an organisation).
     */
    protected function createOrganisationAsset(?Organisation $organisation, string $name = 'foto.jpg'): Asset
    {
        $asset = new Asset();
        $asset->name = $name;
        $asset->mimetype = 'image/jpeg';
        $asset->type = 'image';
        $asset->asset_key = uniqid('asset', true);
        $asset->organisation_id = $organisation ? $organisation->id : null;
        $asset->save();

        return $asset;
    }

    /**
     * A blog post with one translation. $publishedAt drives the dated URL.
     */
    protected function createPost(
        Organisation $organisation,
        string $slug,
        ?Carbon $publishedAt,
        string $body = '<p>Body</p>',
        string $locale = 'nl',
        bool $published = true
    ): Post {
        $post = new Post();
        $post->organisation()->associate($organisation);
        $post->published_at = $publishedAt;
        $post->save();

        $this->createPostTranslation($post, $locale, $slug, $body, $published);

        return $post;
    }

    /**
     * Add a translation to an existing post.
     */
    protected function createPostTranslation(
        Post $post,
        string $locale,
        string $slug,
        string $body = '<p>Body</p>',
        bool $published = true,
        ?string $title = null,
        ?string $author = null
    ): PostTranslation {
        $translation = new PostTranslation();
        $translation->post()->associate($post);
        $translation->locale = $locale;
        $translation->slug = $slug;
        $translation->title = $title ?? ('Post ' . $slug . ' (' . $locale . ')');
        $translation->excerpt = 'Excerpt of ' . $slug;
        $translation->author = $author;
        $translation->body = $body;
        $translation->is_published = $published;
        $translation->save();

        $post->unsetRelation('translations');

        return $translation;
    }
}
