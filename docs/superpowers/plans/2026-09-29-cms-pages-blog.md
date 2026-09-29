# CMS pages and blog (WordPress merge) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve `quizfabriek.be` from this app: block-based pages edited in the admin, imported blog posts at their old `/YYYY/MM/DD/slug/` URLs, per-locale translations (`/`, `/en/`, `/fr/`), scoped per organisation, with 301s, sitemap, hreflang and a domain cutover that retires WordPress.

**Architecture:** `pages` / `posts` hold identity and hierarchy; `page_translations` / `post_translations` hold slug, path, title, blocks JSON or sanitised HTML body, SEO meta and the published flag per locale. A `BlockRegistry` maps block `type` to a `BlockType` class (validation rules, html/asset fields, `prepare()`, Blade view, admin form partial). CMS routes are registered last in `routes/web.php`, once at the root (Dutch) and once under `{locale}`; a `SetCmsLocale` middleware sets the app locale. Admin screens are custom controllers behind the existing `auth` + `admin` middleware with the organisation-admin check of `GuestRegistrationController`. Rich text is sanitised on write with `symfony/html-sanitizer`. Media goes through `\CentralStorage::store()` like `Admin\AssetController`.

**Tech Stack:** Laravel 12 (legacy `app/Http/Kernel.php` layout), PHP 8.5 container, MySQL 8, Blade + Bootstrap 4 + Laravel Mix, PHPUnit 11 (`tests/Integration` on MySQL via `bin/integration-tests.sh`, `tests/Unit` DB-free), TinyMCE 6 (npm, self-hosted), `symfony/html-sanitizer`.

**Spec:** `docs/superpowers/specs/2026-09-29-cms-pages-blog-design.md`

## Global Constraints

- One branch per phase off `master`, PR per phase, phases in order. Each phase leaves production working with no visible change until phase 6.
- Run integration tests with `bin/integration-tests.sh --filter <Test>`; unit tests with `vendor/bin/phpunit --testsuite Unit`. Add `php artisan route:cache && php artisan route:clear` as a CI step in phase 1 (deploy caches routes, `build/upgrade.sh`).
- No closures in routes (route cache). CMS routes are the **last** thing in `routes/web.php`; leave the comment that says so.
- Every field printed with `{!! !!}` must come from a `BlockType::htmlFields()` field or `post_translations.body`, i.e. through `HtmlSanitizer`. Plain fields use `{{ }}`.
- All admin controllers resolve records through `Auth::user()->getActiveOrganisation()` and require `$organisation->isAdmin($user)`; wrong organisation is a 404, never a 403 (matches `GuestRegistrationController::getEventInOrganisation()`).
- Public controllers scope through `organisation()` (`app/helpers.php`). Never query pages or posts without `organisation_id`.
- Admin copy is Dutch (as in `admin/guests/index.blade.php`). Public chrome strings go through `resources/lang/{nl,en,fr}/cms.php`.
- PHPUnit 11: attributes or docblocks both fine; the existing suite uses plain `test*` methods, follow that.
- `vendor/` is not committed; verify package APIs (`CentralStorage::store()`, the facade accessor, Charon frontend) in the container when a task touches them.
- Commit trailer:
  `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>` and
  `Claude-Session: https://claude.ai/code/session_01K4HSjKihM37h2veThmPykf`

---

# Phase 1 — Foundation: schema, models, sanitiser, blocks, public pages

Branch `feature/cms-foundation`. After this phase a page can only be created by hand (tinker/test), but the full public rendering path exists and is tested.

### Task 1.1: Config, migrations, models

**Files:**
- Create: `config/cms.php`
- Create: `database/migrations/2026_10_01_100000_create_pages_tables.php`
- Create: `database/migrations/2026_10_01_100100_create_posts_tables.php`
- Create: `database/migrations/2026_10_01_100200_create_cms_redirects_table.php`
- Create: `database/migrations/2026_10_01_100300_add_home_page_id_to_organisations.php` (nullable FK to `pages`, `nullOnDelete`)
- Create: `app/Models/Page.php`, `app/Models/PageTranslation.php`, `app/Models/Post.php`, `app/Models/PostTranslation.php`, `app/Models/CmsRedirect.php`
- Modify: `app/Models/Organisation.php` (add `pages()`, `posts()`, `cmsRedirects()`, `homePage()` relations; add `resetRepresentedOrganisation()` test helper next to `getRepresentedOrganisation()` at line 272)
- Test: `tests/Integration/Cms/CmsSchemaTest.php`, `tests/Unit/Cms/PagePathTest.php`

**Interfaces:**
- Produces: the five tables from the spec, models with `translations()`, `translation(string $locale)`, `PageTranslation::rebuildPath()`, `PageTranslation::getUrl()`, `PostTranslation::getUrl()`, scopes `published()` and `forOrganisation()`.

- [ ] **Step 1: Failing schema test** — `CmsSchemaTest` creates an organisation (`CreatesEventFixtures::createOrganisation()`), a page with an `nl` translation at path `over-ons`, a child page `in-de-pers` and asserts `child->translation('nl')->path === 'over-ons/in-de-pers'`; renames the parent slug to `about` and asserts the child path became `about/in-de-pers`; asserts the unique index rejects a second `nl` translation with the same path in the same organisation but allows it in another organisation.
- [ ] **Step 2: Migrations.** Follow `2026_09_29_100000_add_guests_to_orders.php` (class-style migration, a docblock explaining why). Columns and indexes exactly as in the spec's Data model. `blocks` is `$table->json('blocks')`. Foreign keys to `organisations`, `pages`, `posts`, `assets`. Soft deletes on `pages` and `posts`.
- [ ] **Step 3: Models.** Extend `CatLab\Charon\Laravel\Database\Model`. `PageTranslation`:

```php
protected $casts = [ 'blocks' => 'array', 'is_published' => 'bool', 'published_at' => 'datetime' ];

/** parent path + '/' + slug. Cascades to children in the same locale. */
public function rebuildPath(): void
{
    $parent = $this->page->parent ? $this->page->parent->translation($this->locale) : null;
    $this->path = trim(($parent ? $parent->path . '/' : '') . $this->slug, '/');
}

public function getUrl(): string
{
    $prefix = $this->locale === config('cms.default_locale') ? '' : '/' . $this->locale;
    return rtrim(url($prefix . '/' . $this->path), '/');
}
```

`saved` hook: when `slug` or the page's `parent_id` changed, load every child page, rebuild the same-locale translation and save (recursion handles depth). `PostTranslation::getUrl()` uses `$this->post->published_at->setTimezone('Europe/Brussels')->format('Y/m/d')`.
- [ ] **Step 4: `Organisation::resetRepresentedOrganisation()`** — sets the static to `null`; docblock says "tests only". Add relations.
- [ ] **Step 5: `config/cms.php`** with `locales`, `default_locale`, `reserved_slugs` (list from the spec), `blocks` (empty for now), `posts_per_page`, `sanitizer` allow-lists, `gone` paths.
- [ ] **Step 6: GREEN**, then `bin/integration-tests.sh` whole suite (migrations must run clean on MySQL 8). Commit `"CMS: pages, posts and redirects schema and models"`.

### Task 1.2: HTML sanitiser

**Files:**
- Modify: `composer.json` (require `symfony/html-sanitizer`)
- Create: `app/Cms/HtmlSanitizer.php`
- Create: `app/Rules/SafeUrl.php`
- Test: `tests/Unit/Cms/HtmlSanitizerTest.php`, `tests/Unit/Cms/SafeUrlTest.php`

**Interfaces:**
- Produces: `App\Cms\HtmlSanitizer::sanitize(string $html): string` (container singleton, config from `config('cms.sanitizer')`), `App\Rules\SafeUrl` (Laravel validation rule).

- [ ] **Step 1: Failing tests.** Cases from the spec's testing table: script/onerror/javascript:/style/foreign iframe/foreign img stripped; YouTube (`www.youtube-nocookie.com`, `www.youtube.com`) iframe kept; asset image on `config('centralstorage.front')` host kept (set the config in the test); `wp-block-gallery` class kept, `elementor-widget` class dropped; `<h1>` becomes `<h2>`; `target="_blank"` gains `rel="noopener noreferrer"`; relative `href="/calendar"`, `mailto:`, `tel:` kept. `SafeUrlTest`: accepts `/x`, `#top`, `https://…`, `mailto:a@b`, `tel:+32…`; rejects `javascript:…`, `data:…`, `ftp://`, `//evil`.
- [ ] **Step 2: Implement** with `Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig`: `allowElement()` per spec, `allowAttribute('class', ...)` then a `preg_replace_callback` pass that keeps only the allowed class prefixes (the Symfony sanitizer has no class filter; do it after sanitising, on the output DOM via `DOMDocument`, or accept a whitelist regex on the string — pick DOM, it is safer), `h1` → `h2` via `DOMDocument` before sanitising, `allowedLinkSchemes`, `allowRelativeLinks`, `allowedMediaHosts`, `forceHttpsUrls(false)`, `withMaxInputLength(2 * 1024 * 1024)`. Verify in the container that Symfony treats `iframe[src]` as a media attribute (it does in `HtmlSanitizer\Reference\W3CReference`); if not, filter iframe hosts in the same DOM pass.
- [ ] **Step 3: GREEN.** Commit `"CMS: HTML sanitiser and SafeUrl rule"`.

### Task 1.3: Block registry, block types and renderer

**Files:**
- Create: `app/Cms/Blocks/BlockType.php`, `app/Cms/Blocks/BlockRegistry.php`, `app/Cms/Blocks/BlockContext.php`, `app/Cms/BlockRenderer.php`, `app/Cms/BlockValidator.php`
- Create: `app/Cms/Blocks/Types/{Hero,RichText,TextImage,Cards,LogoGrid,Reviews,Cta,Video,UpcomingEvents,LatestPosts,Faq}.php` (LatestPosts renders an empty section until phase 3 gives it posts)
- Create: `resources/views/cms/blocks/{hero,rich_text,text_image,cards,logo_grid,reviews,cta,video,upcoming_events,latest_posts,faq}.blade.php`
- Create: `resources/assets/sass/cms.scss`; Modify: `resources/assets/sass/app.scss` (import it)
- Create: `app/Providers/CmsServiceProvider.php` (binds `BlockRegistry` and `HtmlSanitizer` singletons); Modify: `config/app.php` providers, `config/cms.php` `blocks` map
- Test: `tests/Unit/Cms/BlockValidatorTest.php`, `tests/Unit/Cms/BlockRegistryTest.php`

**Interfaces:**
- Produces: `BlockType` abstract (spec's signature), `BlockRegistry::get(string $type): ?BlockType`, `BlockRegistry::all(): array`, `BlockValidator::rules(array $blocks): array`, `BlockValidator::normalise(array $blocks): array` (drops unknown keys, sanitises `htmlFields()`), `BlockRenderer::render(PageTranslation $t, BlockContext $ctx): array` of `['view' => ..., 'data' => ..., 'block' => ...]`.

- [ ] **Step 1: Failing unit tests.** `BlockValidatorTest`: unknown type → rule failure on `blocks.0.type`; `hero` with 4 buttons fails on `blocks.0.data.buttons`; `rich_text` html is sanitised by `normalise()` (bind the real sanitiser); unknown key `data.evil` removed; 41 blocks fail. `BlockRegistryTest`: every class in `config('cms.blocks')` resolves and its `type()` equals its key; every type has an existing `view()` and `formView()` (use `View::exists()` — needs the app, keep in Unit since views need no DB).
- [ ] **Step 2: Implement `BlockType`, `BlockRegistry`, `BlockValidator`.** Rules per type, from the spec's table, e.g. Hero:

```php
public function rules(): array
{
    return [
        'title' => 'required|string|max:120',
        'subtitle' => 'nullable|string|max:300',
        'image_id' => 'nullable|integer|exists:assets,id',
        'align' => 'required|in:left,center',
        'buttons' => 'array|max:3',
        'buttons.*.label' => 'required|string|max:40',
        'buttons.*.url' => ['required', new SafeUrl()],
        'buttons.*.style' => 'required|in:primary,secondary',
    ];
}
public function assetFields(): array { return ['image_id']; }
```

`UpcomingEvents::prepare()` runs the `EventController@calendar` query scoped to `$ctx->organisation`, filtered on `event_type` unless `all`, `limit`, `show_sold_out`. `Video` turns a YouTube URL into a `youtube-nocookie.com/embed/{id}` URL in `prepare()` (never trust the stored URL at render time).
- [ ] **Step 3: Views.** Bootstrap 4 markup consistent with `series/view.blade.php` and `blocks/eventtable.blade.php` (reuse the latter via `@include('blocks.eventtable', ['events' => $data['events']])`). `rich_text` prints `{!! $data['html'] !!}` inside `<div class="cms-prose">`. Images through `$asset->getUrl(['width' => …])` with `loading="lazy"`.
- [ ] **Step 4: GREEN.** Commit `"CMS: block registry, eleven block types and renderer"`.

### Task 1.4: Locale middleware, public routes, page rendering, per-organisation home page

**Files:**
- Create: `app/Http/Middleware/SetCmsLocale.php`; Modify: `app/Http/Kernel.php` (`'cms.locale'` route middleware)
- Create: `app/Http/Controllers/PageController.php`, `app/Http/Controllers/RedirectController.php`
- Create: `resources/views/cms/page.blade.php`, `resources/views/cms/partials/{head,alternates,jsonld}.blade.php`
- Modify: `routes/web.php` (CMS group at the end, `Route::fallback`), `app/Http/Controllers/EventController.php` (`index()` line 81: CMS home first), `resources/views/layouts/blocks/head.blade.php` (`@stack('head')`), `resources/views/layouts/home.blade.php` (no change needed if head is stacked)
- Test: `tests/Integration/Cms/PageRoutingTest.php`, `tests/Integration/Cms/HomePageTest.php`, `tests/Integration/Cms/BlockRenderingTest.php`, `tests/Integration/Cms/ReservedSlugsTest.php`, `tests/Integration/Concerns/CreatesCmsFixtures.php`

**Interfaces:**
- Produces: routes as in the spec; `PageController::homeFor(Organisation, string $locale): ?PageTranslation` (reads `organisations.home_page_id`, returns its published translation for the locale or null); `CreatesCmsFixtures::createPage(Organisation, string $path, array $blocks = [], string $locale = 'nl', bool $published = true): Page`, `createOrganisationDomain(Organisation, string $host)`, `actAsHost(string $host)` (sets `$_SERVER['HTTP_HOST']` and calls `resetRepresentedOrganisation()`).

- [ ] **Step 1: Failing tests** per the spec's `PageRoutingTest` / `HomePageTest` / `BlockRenderingTest` rows. `ReservedSlugsTest`: collects the first segment of every URI in `Route::getRoutes()` (excluding parameters and the CMS routes themselves, identified by controller class) and asserts each is in `config('cms.reserved_slugs')`.
- [ ] **Step 2: Routes** — append to `routes/web.php`:

```php
// ---------------------------------------------------------------------------
// CMS (pages, blog). MUST stay last: the page route is a catch-all and any
// route registered after it is shadowed. New top-level paths also belong in
// config/cms.php 'reserved_slugs' (ReservedSlugsTest enforces it).
// ---------------------------------------------------------------------------
\App\Http\Controllers\PageController::routes();
Route::fallback('RedirectController@fallback');
```

with `PageController::routes()` doing the two-group registration from the spec (`Route::middleware('cms.locale')->group(...)` and `Route::prefix('{locale}')->where(['locale' => implode('|', $nonDefault)])->middleware('cms.locale')->group(...)`), all handlers as controller strings.
- [ ] **Step 3: Middleware** — as the spec describes; `forgetParameter('locale')`.
- [ ] **Step 4: `PageController@show`**: lookup, published check, preview for org admins (`abort_unless`), `cms_redirects` on miss (helper in `RedirectController`/`App\Cms\Redirects`), render `cms.page` with `translation`, `blocks` (from `BlockRenderer`), `alternates` (published translations), `canonicalUrl`, `description`. `EventController@index`: first lines become

```php
$organisation = $this->getOrganisation();
if ($organisation && ($home = PageController::homeFor($organisation, config('cms.default_locale')))) {
    return app(PageController::class)->render($request, $home);
}
```

`PageController@show` 301s to the locale root when the resolved page is the organisation's `home_page_id`; `PageController@home` (`/en`, `/fr`) 404s when `homeFor()` returns null.
- [ ] **Step 5: Views.** `cms/page.blade.php` extends `layouts/home`, `@section('title')`, `@section('description')`, `@push('head')` with canonical + alternates + OG, `@section('jsonld-content')` with `WebPage`. `head.blade.php` gains `@stack('head')` after the canonical block (leave the existing `$canonicalUrl` handling; CMS pages pass none and push their own).
- [ ] **Step 6: GREEN**, full integration suite, `php artisan route:cache` succeeds. Commit `"CMS: locale middleware, public page routes and rendering"`.

### Task 1.5: CI: route cache check

**Files:** `.github/workflows/tests.yml`

- [ ] Add a step after the tests: `php artisan route:cache && php artisan route:clear` (fails the job if a closure sneaks into the routes). Commit `"CI: verify routes are cacheable"`.

---

# Phase 2 — Admin editor for pages

Branch `feature/cms-admin-pages`.

### Task 2.1: Policies, admin routes, pages index

**Files:**
- Create: `app/Policies/PagePolicy.php` (copy the shape of `SeriesPolicy`: `index/create(User, Organisation)`, `view/edit/destroy(User, Page)` via `organisation->isAdmin`); Modify: `app/Providers/AuthServiceProvider.php` (`$policies`)
- Create: `app/Http/Controllers/Admin/PageController.php` (`index`, `create`, `store`, `edit`, `update`, `destroy`, `destroyTranslation`, `createTranslation`, `setHome`)
- Modify: `app/Http/Api/V1/ResourceDefinitions/OrganisationResourceDefinition.php` (writeable `home_page_id` "Startpagina" field, validated to a page of the same organisation)
- Create: `resources/views/admin/cms/pages/index.blade.php`
- Modify: `routes/web.php` (inside the `admin` group, near line 86: `Route::get('pages', 'Admin\PageController@index')` etc., plain routes, no `::routes()` since this is not a Charon controller), `resources/views/layouts/admin.blade.php` ("Website" sidebar section with Pages)
- Test: `tests/Integration/Cms/AdminPagesTest.php` (index cases), extend `tests/Integration/AdminSmokeTest.php` with `/admin/pages`

- [ ] **Step 1: Failing tests**: anonymous → login redirect; logged-in non-admin → `IsAdmin` redirect to `/`; admin of organisation A sees A's pages and not B's; index shows locale badges; "Stel in als startpagina" sets `organisations.home_page_id` and shows the "Startpagina" badge; setting another organisation's page is a 404; deleting the selected home page is refused.
- [ ] **Step 2: Implement.** `getPageInOrganisation($id)` helper mirroring `getEventInOrganisation()`. Index builds a tree from `Page::forOrganisation()->with('translations')->orderBy('sort_order')`.
- [ ] **Step 3: GREEN.** Commit `"Admin: pages index"`.

### Task 2.2: Page + translation form with block editor

**Files:**
- Create: `resources/views/admin/cms/pages/edit.blade.php`, `resources/views/admin/cms/pages/_translation_form.blade.php`, `resources/views/admin/cms/blocks/{hero,rich_text,text_image,cards,logo_grid,reviews,cta,video,upcoming_events,latest_posts,faq}.blade.php` (each renders its fields for index `{{ $i }}`; also used inside `<template>` with `__INDEX__` placeholders)
- Create: `resources/assets/js/cms-editor.js`; Modify: `webpack.mix.js` (add to the `admin.js` bundle; copy `node_modules/tinymce` to `public/js/tinymce`), `package.json` (`tinymce@^6`)
- Create: `app/Http/Requests/Admin/PageTranslationRequest.php` (FormRequest: page fields + translation fields + `BlockValidator::rules()`; `slug` rules: `regex:/^[a-z0-9\-]*$/`, top-level not in reserved slugs, never empty; `locale` in `config('cms.locales')`)
- Modify: `app/Http/Controllers/Admin/PageController.php` (`store`, `update`, `createTranslation`, `destroyTranslation`, `copyTranslation`)
- Test: `tests/Integration/Cms/AdminPagesTest.php` (form cases), extend `AdminSmokeTest` with `/admin/pages/create` and `/admin/pages/{id}/edit/nl`

- [ ] **Step 1: Failing tests** from the spec's `AdminPagesTest` row: create page with nl translation and two blocks; add en translation; reserved top-level slug rejected (`events`); duplicate path rejected; invalid block data re-renders with errors; `rich_text` html stored sanitised; slug rename cascades `path`; "copy from nl" clones blocks with fresh ids; sitemap cache key forgotten (assert `Cache::has()` false after save).
- [ ] **Step 2: Form.** Use the `$errors->all()` alert of `admin/guests/index.blade.php`. Locale tabs are links to `/admin/pages/{id}/edit/{locale}`; missing locales link to `createTranslation`. Blocks section: existing blocks rendered from `old('blocks', $translation->blocks)` so validation errors keep the editor state; a `<template data-block-type="hero">` per registered type; the add-block `<select>` lists `BlockRegistry::all()` labels.
- [ ] **Step 3: `cms-editor.js`**: add / remove / up / down for blocks and repeater rows, re-index `name` attributes (`blocks[3][data][items][1][title]`), initialise TinyMCE on `textarea.cms-html` (also after adding a block), config per spec (upload URL comes in Task 2.3; until then `images_upload_url` is unset).
- [ ] **Step 4: Controller `store`/`update`**: validate through the FormRequest, `BlockValidator::normalise()`, fill, `rebuildPath()`, save inside a transaction, `Cache::forget(SitemapController::CACHE_KEY . ':' . $organisation->id)` (introduce the per-organisation key in `SitemapController` now: constant becomes a method `cacheKey(Organisation)`), redirect back with `message`.
- [ ] **Step 5: GREEN.** Commit `"Admin: page editor with section blocks"`.

### Task 2.3: Image upload endpoint and asset picker

**Files:**
- Create: `app/Http/Controllers/Admin/CmsAssetController.php` (`upload`, `index`)
- Modify: `routes/web.php` (admin group: `POST cms/upload`, `GET cms/assets`), `cms-editor.js` (TinyMCE `images_upload_url`, block image picker modal that lists `GET admin/cms/assets` and lets the admin upload a new one), `resources/views/admin/cms/blocks/_image_field.blade.php` (hidden `image_id` + thumbnail + "Kies afbeelding")
- Create: `tests/Integration/Fakes/FakeCentralStorage.php` (bind in `IntegrationTestCase::setUp()` like `FakeEuklesClient`; inspect `vendor/catlabinteractive/central-storage-client` for the facade accessor and the `store()` return type, and make the fake create a real `Asset` row so `getUrl()` works)
- Test: `tests/Integration/Cms/AdminUploadTest.php`

- [ ] **Step 1: Failing tests**: anonymous POST → redirect; admin uploads a PNG (`UploadedFile::fake()->image()`) → 200 JSON `{id, location}` and an `assets` row; a `.php` upload → 422; `GET admin/cms/assets` lists images only.
- [ ] **Step 2: Implement** (`$request->validate(['file' => 'required|image|max:10240'])`, `\CentralStorage::store($file)`, `$asset->user()->associate(Auth::user())` if the model has it, JSON response). Decide the asset scoping from open question 5 (default: assets whose `user_id` is an admin of the active organisation).
- [ ] **Step 3: GREEN.** Commit `"Admin: image upload and picker for the page editor"`.

---

# Phase 3 — Blog posts

Branch `feature/cms-posts`.

### Task 3.1: Public blog routes and views

**Files:**
- Create: `app/Http/Controllers/PostController.php` (`show`), Modify: `app/Http/Controllers/PageController.php` (`blogIndex`)
- Create: `resources/views/cms/blog/index.blade.php`, `resources/views/cms/blog/show.blade.php`, `resources/views/cms/blog/_card.blade.php`
- Modify: `app/Cms/Blocks/Types/LatestPosts.php` + view (real query, cached 5 min per organisation + locale), `resources/views/layouts/blocks/blog.blade.php` (rewrite: latest local posts, drop `Feeds`; keep the include in `series/view.blade.php:334`; block renders nothing when the organisation has no posts), `resources/views/layouts/blocks/navigation.blade.php` (the "Blog" item links to `/blog` when the organisation has published posts, else `organisation()->blog_url` as today)
- Create: `resources/lang/{nl,en,fr}/cms.php` (`blog`, `read_more`, `published_on`, `older_posts`, `newer_posts`, `no_posts`, `in_other_languages`)
- Test: `tests/Integration/Cms/PostRoutingTest.php`, `tests/Integration/Cms/LocaleTest.php`; extend `CreatesCmsFixtures` with `createPost(Organisation, string $slug, Carbon $publishedAt, string $body, string $locale = 'nl', bool $published = true): Post`

- [ ] **Step 1: Failing tests** from the spec's `PostRoutingTest` and `LocaleTest` rows (including: the series page renders with the blog block when posts exist and when none exist).
- [ ] **Step 2: Implement.** `PostController@show`: find by `(organisation_id, locale, slug)` joined on `posts.published_at <= now()`; compare `Y/m/d` in Europe/Brussels; mismatch → `redirect($translation->getUrl(), 301)`. Blog index paginates `config('cms.posts_per_page')` with `vendor/pagination/bootstrap-4`. Post view: featured image (`getUrl(['width' => 1280])`), date via `translatedFormat('j F Y')`, `{!! $translation->body !!}` in `.cms-prose`, alternates + canonical + `BlogPosting` JSON-LD pushed to `head`.
- [ ] **Step 3: GREEN.** Commit `"CMS: blog index, post pages and local latest-posts block (replaces the RSS block)"`.

### Task 3.2: Admin for posts

**Files:**
- Create: `app/Policies/PostPolicy.php`; Modify: `AuthServiceProvider`
- Create: `app/Http/Controllers/Admin/PostController.php`, `app/Http/Requests/Admin/PostTranslationRequest.php`
- Create: `resources/views/admin/cms/posts/{index,edit}.blade.php`
- Modify: `routes/web.php` (admin group), `layouts/admin.blade.php` (sidebar: Posts)
- Test: `tests/Integration/Cms/AdminPostsTest.php`; extend `AdminSmokeTest`

- [ ] **Step 1: Failing tests**: create a post (date, featured image id, nl slug/title/excerpt/body), body sanitised, publish toggle, add fr translation, duplicate `(locale, slug)` rejected, other organisation's post 404.
- [ ] **Step 2: Implement** on the pattern of the pages controller; the body textarea uses the same TinyMCE init (`textarea.cms-html`).
- [ ] **Step 3: GREEN.** Commit `"Admin: blog post editor"`.

---

# Phase 4 — WordPress import

Branch `feature/wordpress-import`.

### Task 4.1: Importer service and command

**Files:**
- Create: `app/Cms/Import/WordPressImporter.php` (HTTP through `Illuminate\Support\Facades\Http`, base URL from the constructor), `app/Cms/Import/MediaImporter.php` (download → temp file → `UploadedFile` → `\CentralStorage::store()` → `Asset`; remembers URL → asset per run, strips `-\d+x\d+` before the extension and retries the original), `app/Cms/Import/ContentRewriter.php` (DOM pass: `img[src]`/`a[href]` pointing at `{site}/wp-content/uploads/…` → asset URL, drop `srcset`/`sizes`, unwrap Elementor containers for pages)
- Create: `app/Console/Commands/WordPressImport.php` (`wordpress:import`, options per spec, registered by `app/Console/Kernel.php` if commands are listed explicitly there — check)
- Create: `tests/Integration/Cms/WordPressImportTest.php` + fixtures `tests/Integration/Cms/fixtures/wp-{posts,pages,media}.json` (two posts with a gallery + YouTube embed, one page tree incl. a `-nieuw` draft, one media item)
- Test also: `tests/Unit/Cms/ContentRewriterTest.php`

- [ ] **Step 1: Failing tests** from the spec's `WordPressImportTest` row, using `Http::fake([...])` and `FakeCentralStorage`. Assert: `posts.wp_post_id` set, `published_at` equals the WP `date` interpreted in Europe/Brussels, body contains the asset URL and no `wp-content/uploads`, `cms_redirects` has `wp-content/uploads/2019/03/foto.jpg`, second run changes nothing (`assertDatabaseCount`), `-nieuw` skipped, page tree parent set and translation unpublished with one `rich_text` block, `--dry-run` writes nothing.
- [ ] **Step 2: Implement.** Pagination via the `X-WP-TotalPages` header. Log dropped elements per post (compare tag counts before/after sanitising) and print them at the end. Retries on media download (3, backoff). Everything inside one transaction per post.
- [ ] **Step 3: GREEN.** Commit `"wordpress:import — posts, pages and media from the WP REST API"`.

### Task 4.2: Run the import on production (operations, not code)

- [ ] `php artisan wordpress:import --organisation=<Quizfabriek id> --posts --dry-run`, review the mapping, then without `--dry-run`. Then `--pages`. Then `--redirects` and hand the printed unmapped URL list to whoever fills the redirect table (phase 5 admin) — most are `/category/…` and `/feed/`.
- [ ] Spot-check three posts on `tickets.quizfabriek.be/2019/…` (they render there already; canonical will move at cutover).
- [ ] Editors rebuild the nine pages from the imported `rich_text` drafts using blocks; keep them **unpublished** except for previewing.

---

# Phase 5 — SEO plumbing, redirects admin, canonical domain

Branch `feature/cms-seo-cutover-prep`.

### Task 5.1: Sitemap, hreflang, language switcher, chrome strings

**Files:**
- Modify: `app/Http/Controllers/SitemapController.php` (per-organisation cache key, page/post entries with alternates, blog index), `resources/views/sitemap.blade.php` (`xmlns:xhtml`, alternates loop), `resources/views/layouts/blocks/navigation.blade.php` (CMS menu from `show_in_menu` pages of the current locale, cached 5 min; language switcher from `$alternates` when ≥ 2), `resources/views/layouts/blocks/footer.blade.php` (labels through `__('cms.*')`), `resources/lang/{nl,en,fr}/cms.php`
- Create: `app/Cms/CmsCacheObserver.php` (forget menu/latest-posts/sitemap caches on page/post save and delete; register in `CmsServiceProvider`)
- Test: extend `tests/Integration/SitemapTest.php`, create `tests/Integration/Cms/SeoTest.php`

- [ ] **Step 1: Failing tests** per the spec's `SeoTest` and `SitemapTest` rows. The sitemap test must also prove that organisation B's host does not get organisation A's page URLs (uses `actAsHost`).
- [ ] **Step 2: Implement.** Decide open question 9 before touching the event entries; the CMS entries are scoped regardless.
- [ ] **Step 3: GREEN.** Commit `"CMS: sitemap entries, hreflang, language switcher and menu"`.

### Task 5.2: Redirects: fallback route, 410 list, admin screen, rename helper

**Files:**
- Modify: `app/Http/Controllers/RedirectController.php` (`fallback`: `cms_redirects` → `?p=` shortlink → `config('cms.gone')` 410 → 404), `app/Http/Controllers/PageController.php` (miss path uses the same resolver)
- Create: `app/Cms/Redirects.php` (`resolve(Organisation, string $path): ?RedirectResponse`)
- Create: `app/Http/Controllers/Admin/RedirectController.php` (`index`, `store`, `destroy`), `resources/views/admin/cms/redirects/index.blade.php`; Modify: `routes/web.php`, `layouts/admin.blade.php`
- Modify: `resources/views/admin/cms/pages/_translation_form.blade.php` + `Admin\PageController@update` ("maak een redirect van het oude adres" checkbox, checked by default when a published slug changes; writes the row for the old path and, recursively, for the children's old paths)
- Test: `tests/Integration/Cms/RedirectTest.php`, `AdminPagesTest` (rename creates redirects)

- [ ] Failing tests → implement → GREEN. Commit `"CMS: redirect table, fallback route, 410 for WordPress paths, redirects admin"`.

### Task 5.3: Canonical domain middleware

**Files:**
- Create: `database/migrations/2026_10_15_100000_add_is_canonical_to_organisation_domains.php`
- Create: `app/Http/Middleware/CanonicalDomain.php`; Modify: `app/Http/Kernel.php` (`web` group, right after `ValidDomain`)
- Modify: `app/Models/Organisation.php` (`canonicalDomain()`), `app/Http/Api/V1/ResourceDefinitions/OrganisationResourceDefinition.php` only if domains are edited through the admin (they are not today: rows are inserted by hand; leave it and document the SQL in the cutover task)
- Test: `tests/Integration/Cms/CanonicalDomainTest.php`

- [ ] **Step 1: Failing tests**: with domains `example.test` (canonical) and `tickets.example.test`, `GET http://tickets.example.test/over-ons?x=1` → 301 `https://example.test/over-ons?x=1` (scheme from `X-Forwarded-Proto`, else the request's); `/status` untouched; `live.example.test` untouched (the livestream host convention in `Organisation::getFromDomainOrFirst()`); POST untouched; an organisation without a canonical domain untouched.
- [ ] **Step 2: Implement** (GET/HEAD only, skip `status`, skip hosts starting with `live.`, compare against `organisation()->canonicalDomain()`).
- [ ] **Step 3: GREEN.** Commit `"Per-organisation canonical domain redirect"`. Leave `ValidDomain` as is unless open question 11 says delete.

---

# Phase 6 — Cutover

Branch `chore/quizfabriek-cutover` for the code bits; the rest is operations. Do it on a weekday morning with WordPress still running.

### Task 6.1: Code

- [ ] Canonical host is `www.quizfabriek.be`; cookie consent stays the existing CatLab one (CookieYes is dropped). If a separate GTM container is needed: migration `organisations.gtm_container_id`, `layouts/blocks/gtag.blade.php` reads it, `OrganisationResourceDefinition` exposes it (writeable admin field).
- [ ] Update the default OG image and description fallbacks in `resources/views/layouts/blocks/seo.blade.php` to read `organisation()` fields rather than the hard-coded `www.quizfabriek.be` URL (the old WordPress host will 301 into the app; the image must exist as an asset).
- [ ] `readme.md` "Domains" list: add `quizfabriek.be`, `www.quizfabriek.be`; document `wordpress:import` and the canonical flag.
- [ ] Commit `"Quizfabriek cutover: OG defaults from organisation, docs"`.

### Task 6.2: Operations checklist

- [ ] Insert `organisation_domains` rows for `quizfabriek.be` and `www.quizfabriek.be` (organisation = Quizfabriek), `is_canonical = 1` on `www.quizfabriek.be`. Add both to `VALID_DOMAINS` and the TLS certificate; confirm the reverse proxy forwards `X-Forwarded-Host`/`Proto` (`AppServiceProvider::boot()` relies on them).
- [ ] Publish the nine Dutch page translations and the home page, and select the home page as the organisation's "Startpagina" (`home_page_id`). Check `/` on `tickets.quizfabriek.be` now shows the CMS home and `/events` still works.
- [ ] Fill remaining `cms_redirects` from the phase 4 list; confirm `/?p=<id>` and one `wp-content/uploads` URL redirect.
- [ ] Lower the DNS TTL a day ahead; switch `quizfabriek.be` / `www` A/AAAA records to the Laravel host.
- [ ] Verify from outside: home, nine pages, three posts, `/sitemap.xml`, `/en/…` 404 (no translations yet is fine), `tickets.quizfabriek.be/e/…` → 301 to the canonical host, a full ticket purchase, `/admin`.
- [ ] Search Console: add the property if missing, submit `/sitemap.xml`, watch coverage for a week.
- [ ] After two weeks: shut down WordPress, cancel Elementor Pro, remove `blog_url` / `blog_rss_url` values from the organisation (columns stay).

---

# Optional follow-ups (not scheduled)

- `/blog/feed` RSS output (open question 10).
- Sanitise `organisations.footer_html` (open question 8).
- Delete `ValidDomain` (open question 11).
- Translations of the ticketing pages, now that `SetCmsLocale` and the lang files exist.
- A `FaqPage` JSON-LD emitter for the `faq` block.
