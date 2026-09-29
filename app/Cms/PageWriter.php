<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Cms;

use App\Http\Controllers\SitemapController;
use App\Models\Organisation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Rules\OrganisationAsset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for CMS pages and their translations. The admin
 * editor and the Charon API both go through here, so validation (block
 * rules, slug format, reserved and duplicate paths, same-organisation
 * parents and assets), sanitising, path rebuilding and cache busting are
 * identical everywhere.
 *
 * Errors are thrown as Illuminate ValidationExceptions keyed by field
 * (`slug`, `blocks.2.data.title`, `parent_id`, ...). The admin lets Laravel
 * turn them into a redirect with errors, the API maps them onto a Charon
 * ResourceValidationException.
 *
 * Class PageWriter
 * @package App\Cms
 */
class PageWriter
{
    /**
     * Fields of a translation that can be written.
     */
    const TRANSLATION_FIELDS = [
        'slug', 'title', 'meta_title', 'meta_description', 'og_image_id', 'is_published', 'blocks',
    ];

    /**
     * Fields of a page that can be written.
     */
    const PAGE_FIELDS = [ 'parent_id', 'sort_order', 'show_in_menu' ];

    /**
     * @var BlockValidator
     */
    private $blockValidator;

    /**
     * @param BlockValidator $blockValidator
     */
    public function __construct(BlockValidator $blockValidator)
    {
        $this->blockValidator = $blockValidator;
    }

    /**
     * Create a page and its first translation.
     * @param Organisation $organisation
     * @param array $pageAttributes parent_id, sort_order, show_in_menu
     * @param string $locale
     * @param array $translationAttributes see TRANSLATION_FIELDS
     * @return PageTranslation
     * @throws ValidationException
     */
    public function createPage(
        Organisation $organisation,
        array $pageAttributes,
        string $locale,
        array $translationAttributes
    ): PageTranslation {
        return $this->transaction($organisation, function () use ($organisation, $pageAttributes, $locale, $translationAttributes) {
            $page = new Page();
            $page->organisation()->associate($organisation);
            $this->fillPage($page, $pageAttributes);
            $this->preparePage($page);
            $page->save();

            $translation = new PageTranslation();
            $translation->page()->associate($page);
            $translation->locale = $locale;
            $this->fillTranslation($translation, $translationAttributes);
            $this->prepareTranslation($translation);
            $translation->save();

            $page->unsetRelation('translations');

            return $translation;
        });
    }

    /**
     * Update the page's own fields (hierarchy, order, menu).
     * @param Page $page
     * @param array $attributes
     * @return Page
     * @throws ValidationException
     */
    public function updatePage(Page $page, array $attributes): Page
    {
        return $this->transaction($page->organisation, function () use ($page, $attributes) {
            $this->fillPage($page, $attributes);
            $this->preparePage($page);
            $page->save();

            return $page;
        });
    }

    /**
     * Create or update the translation of $page in $locale.
     * @param Page $page
     * @param string $locale
     * @param array $attributes see TRANSLATION_FIELDS; missing keys keep their value
     * @return PageTranslation
     * @throws ValidationException
     */
    public function saveTranslation(Page $page, string $locale, array $attributes): PageTranslation
    {
        return $this->transaction($page->organisation, function () use ($page, $locale, $attributes) {
            $translation = $page->translations()->where('locale', '=', $locale)->first();
            if (!$translation) {
                $translation = new PageTranslation();
                $translation->locale = $locale;
            }
            $translation->setRelation('page', $page);
            $translation->page_id = $page->id;

            $this->fillTranslation($translation, $attributes);
            $this->prepareTranslation($translation);
            $translation->save();

            $page->unsetRelation('translations');

            return $translation;
        });
    }

    /**
     * Start the $to translation from a copy of the $from translation: same
     * slug, title, meta and blocks (with fresh block ids), unpublished.
     * @param Page $page
     * @param string $from
     * @param string $to
     * @return PageTranslation
     * @throws ValidationException
     */
    public function copyTranslation(Page $page, string $from, string $to): PageTranslation
    {
        $source = $page->translations()->where('locale', '=', $from)->first();
        if (!$source) {
            throw ValidationException::withMessages([
                'locale' => 'Er is geen vertaling "' . $from . '" om van te kopiëren.',
            ]);
        }

        if ($page->translations()->where('locale', '=', $to)->exists()) {
            throw ValidationException::withMessages([
                'locale' => 'Deze pagina heeft al een vertaling "' . $to . '".',
            ]);
        }

        $blocks = array_map(function ($block) {
            if (is_array($block)) {
                $block['id'] = self::newBlockId();
            }
            return $block;
        }, is_array($source->blocks) ? $source->blocks : []);

        return $this->saveTranslation($page, $to, [
            'slug' => $source->slug,
            'title' => $source->title,
            'meta_title' => $source->meta_title,
            'meta_description' => $source->meta_description,
            'og_image_id' => $source->og_image_id,
            'is_published' => false,
            'blocks' => $blocks,
        ]);
    }

    /**
     * @param PageTranslation $translation
     * @throws ValidationException
     */
    public function deleteTranslation(PageTranslation $translation): void
    {
        $this->assertCanDeleteTranslation($translation);

        $organisation = $translation->page->organisation;
        $this->transaction($organisation, function () use ($translation) {
            $translation->delete();
            $translation->page->unsetRelation('translations');
        });
    }

    /**
     * Child pages would keep a path under a parent translation that no
     * longer exists, so they must lose theirs first.
     * @param PageTranslation $translation
     * @throws ValidationException
     */
    public function assertCanDeleteTranslation(PageTranslation $translation): void
    {
        $childTranslations = PageTranslation::query()
            ->where('locale', '=', $translation->locale)
            ->whereHas('page', function ($query) use ($translation) {
                $query->where('parent_id', '=', $translation->page_id);
            })
            ->exists();

        if ($childTranslations) {
            throw ValidationException::withMessages([
                'locale' => 'Deze vertaling heeft onderliggende pagina\'s in dezelfde taal. Verwijder of verplaats die eerst.',
            ]);
        }
    }

    /**
     * Delete a page and its translations. Refused for the organisation's home
     * page and for pages that still have child pages.
     * @param Page $page
     * @throws ValidationException
     */
    public function deletePage(Page $page): void
    {
        $this->assertCanDeletePage($page);

        $this->transaction($page->organisation, function () use ($page) {
            // Translations go for real: they hold the (unique) public path.
            $page->translations()->delete();
            $page->delete();
        });
    }

    /**
     * @param Page $page
     * @throws ValidationException
     */
    public function assertCanDeletePage(Page $page): void
    {
        $organisation = $page->organisation;

        if ($organisation && (int) $organisation->home_page_id === (int) $page->id) {
            throw ValidationException::withMessages([
                'page' => 'Dit is de startpagina van de organisatie. Kies eerst een andere startpagina.',
            ]);
        }

        if ($page->children()->exists()) {
            throw ValidationException::withMessages([
                'page' => 'Deze pagina heeft nog onderliggende pagina\'s. Verwijder of verplaats die eerst.',
            ]);
        }
    }

    /**
     * Select (or with null, clear) the organisation's home page.
     * @param Organisation $organisation
     * @param Page|null $page
     * @throws ValidationException
     */
    public function setHomePage(Organisation $organisation, ?Page $page): void
    {
        if ($page && (int) $page->organisation_id !== (int) $organisation->id) {
            throw ValidationException::withMessages([
                'home_page_id' => 'De startpagina moet een pagina van deze organisatie zijn.',
            ]);
        }

        $this->transaction($organisation, function () use ($organisation, $page) {
            $organisation->home_page_id = $page ? $page->id : null;
            $organisation->save();
        });
    }

    /**
     * Is $pageId a (not deleted) page of $organisation? For the organisation's
     * home_page_id, wherever it is written.
     * @param Organisation $organisation
     * @param mixed $pageId
     * @return bool
     */
    public function isPageOfOrganisation(Organisation $organisation, $pageId): bool
    {
        return is_numeric($pageId)
            && $organisation->exists
            && Page::forOrganisation($organisation)->whereKey((int) $pageId)->exists();
    }

    /**
     * Validate the page's own fields as they are set on the model (the API
     * fills the model before calling this).
     * @param Page $page
     * @throws ValidationException
     */
    public function preparePage(Page $page): void
    {
        $organisation = $page->organisation;
        if (!$organisation) {
            throw ValidationException::withMessages([ 'organisation' => 'Een pagina hoort bij een organisatie.' ]);
        }

        Validator::make([
            'parent_id' => $page->parent_id,
            'sort_order' => $page->sort_order,
            'show_in_menu' => $page->show_in_menu,
        ], [
            'parent_id' => [ 'nullable', 'integer' ],
            'sort_order' => [ 'nullable', 'integer', 'between:-100000,100000' ],
            'show_in_menu' => [ 'nullable', 'boolean' ],
        ])->validate();

        $page->sort_order = (int) $page->sort_order;
        $page->show_in_menu = (bool) $page->show_in_menu;

        if (!$page->parent_id) {
            $page->parent_id = null;
            $page->setRelation('parent', null);
        } else {
            $parent = Page::forOrganisation($organisation)->find((int) $page->parent_id);
            if (!$parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'De bovenliggende pagina bestaat niet (of hoort bij een andere organisatie).',
                ]);
            }

            // No cycles: the parent may not be the page itself or one of its descendants.
            $ancestor = $parent;
            $seen = [];
            while ($ancestor) {
                if ($page->exists && (int) $ancestor->id === (int) $page->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Een pagina kan niet onder zichzelf of een van haar onderliggende pagina\'s hangen.',
                    ]);
                }
                if (isset($seen[$ancestor->id])) {
                    break;
                }
                $seen[$ancestor->id] = true;
                $ancestor = $ancestor->parent;
            }

            $page->setRelation('parent', $parent);
        }

        // A new parent moves every translation: check the new paths.
        if ($page->exists && $page->isDirty('parent_id')) {
            foreach ($page->translations()->get() as $translation) {
                $translation->setRelation('page', $page);
                $translation->rebuildPath();
                $this->assertPathAvailable($translation, 'parent_id');
            }
        }
    }

    /**
     * Validate and normalise a translation as it is set on the model (the
     * API fills the model before calling this): field rules, blocks, the
     * resulting path. Leaves the model ready to save.
     * @param PageTranslation $translation
     * @throws ValidationException
     */
    public function prepareTranslation(PageTranslation $translation): void
    {
        /** @var Page $page */
        $page = $translation->page;
        $organisation = $page ? $page->organisation : null;
        if (!$page || !$organisation) {
            throw ValidationException::withMessages([ 'page' => 'Een vertaling hoort bij een pagina.' ]);
        }

        $blocks = $translation->blocks;
        if ($blocks === null || $blocks === '') {
            $blocks = [];
        }
        if (is_string($blocks)) {
            // JSON text (API clients); the 'blocks' rule rejects anything
            // that does not decode to a list.
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : $blocks;
        }

        $data = [
            'locale' => $translation->locale,
            'slug' => $translation->slug,
            'title' => $translation->title,
            'meta_title' => $translation->meta_title,
            'meta_description' => $translation->meta_description,
            'og_image_id' => $translation->og_image_id,
            'is_published' => $translation->is_published,
            'blocks' => $blocks,
        ];

        $rules = array_merge([
            'locale' => [ 'required', 'string', 'in:' . implode(',', config('cms.locales')) ],
            'slug' => [ 'required', 'string', 'max:191', 'regex:/^[a-z0-9\-]+$/' ],
            'title' => [ 'required', 'string', 'max:255' ],
            'meta_title' => [ 'nullable', 'string', 'max:255' ],
            'meta_description' => [ 'nullable', 'string', 'max:320' ],
            'og_image_id' => [ 'nullable', 'integer', new OrganisationAsset($organisation->id) ],
            'is_published' => [ 'nullable', 'boolean' ],
        ], $this->blockValidator->rules(is_array($blocks) ? $blocks : [], $organisation->id));

        Validator::make($data, $rules, [
            'slug.regex' => 'Het adres (slug) mag alleen kleine letters, cijfers en koppeltekens bevatten.',
        ], [
            'slug' => 'adres (slug)',
            'title' => 'titel',
            'meta_title' => 'SEO-titel',
            'meta_description' => 'SEO-beschrijving',
            'og_image_id' => 'deelafbeelding',
        ])->validate();

        $duplicate = PageTranslation::query()
            ->where('page_id', '=', $page->id)
            ->where('locale', '=', $translation->locale)
            ->when($translation->exists, function ($query) use ($translation) {
                $query->where('id', '!=', $translation->id);
            })
            ->exists();
        if ($page->exists && $duplicate) {
            throw ValidationException::withMessages([
                'locale' => 'Deze pagina heeft al een vertaling "' . $translation->locale . '".',
            ]);
        }

        $translation->blocks = $this->blockValidator->normalise($blocks);
        $translation->og_image_id = $translation->og_image_id ? (int) $translation->og_image_id : null;
        $translation->is_published = (bool) $translation->is_published;
        $translation->meta_title = $translation->meta_title ?: null;
        $translation->meta_description = $translation->meta_description ?: null;

        $translation->rebuildPath();
        $this->assertPathAvailable($translation, 'slug');
    }

    /**
     * The translation's (rebuilt) path must be free in its organisation and
     * locale, fit the column and not start with a reserved segment.
     * @param PageTranslation $translation
     * @param string $errorKey
     * @throws ValidationException
     */
    protected function assertPathAvailable(PageTranslation $translation, string $errorKey): void
    {
        $path = (string) $translation->path;
        $first = explode('/', $path)[0];

        if (in_array($first, config('cms.reserved_slugs'), true)) {
            throw ValidationException::withMessages([
                $errorKey => 'Het adres /' . $first . ' is voorbehouden voor een ander deel van de site. Kies een ander adres.',
            ]);
        }

        if (mb_strlen($path) > 191) {
            throw ValidationException::withMessages([
                $errorKey => 'Het volledige adres /' . $path . ' is te lang.',
            ]);
        }

        $organisationId = $translation->page->organisation_id;

        $taken = PageTranslation::query()
            ->where('organisation_id', '=', $organisationId)
            ->where('locale', '=', $translation->locale)
            ->where('path', '=', $path)
            ->when($translation->exists, function ($query) use ($translation) {
                $query->where('id', '!=', $translation->id);
            })
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                $errorKey => 'Er bestaat al een pagina met het adres /' . $path . ' in deze taal.',
            ]);
        }
    }

    /**
     * Forget everything cached from the organisation's pages.
     * @param Organisation $organisation
     */
    public function forgetCaches(Organisation $organisation): void
    {
        Cache::forget(SitemapController::cacheKey($organisation));
    }

    /**
     * A fresh block id: 6 hex characters.
     * @return string
     */
    public static function newBlockId(): string
    {
        return bin2hex(random_bytes(3));
    }

    /**
     * @param Page $page
     * @param array $attributes
     */
    protected function fillPage(Page $page, array $attributes): void
    {
        foreach (self::PAGE_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $page->{$field} = $attributes[$field];
            }
        }
    }

    /**
     * @param PageTranslation $translation
     * @param array $attributes
     */
    protected function fillTranslation(PageTranslation $translation, array $attributes): void
    {
        foreach (self::TRANSLATION_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $translation->{$field} = $attributes[$field];
            }
        }
    }

    /**
     * Run $callback in a transaction, turn a unique index violation that the
     * checks above could not foresee (a cascaded child path) into a
     * validation error, and forget the caches afterwards.
     * @param Organisation|null $organisation
     * @param callable $callback
     * @return mixed
     * @throws ValidationException
     */
    protected function transaction(?Organisation $organisation, callable $callback)
    {
        try {
            $result = DB::transaction($callback);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'slug' => 'Dit adres, of dat van een onderliggende pagina, bestaat al.',
            ]);
        }

        if ($organisation) {
            $this->forgetCaches($organisation);
        }

        return $result;
    }
}
