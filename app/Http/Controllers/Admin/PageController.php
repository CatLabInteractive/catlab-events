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

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\PageWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageTranslationRequest;
use App\Models\Organisation;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Admin editor for CMS pages: the page tree, and one form per translation
 * with the section block editor. Not a Charon FrontCrudController: the block
 * editor (repeaters, rich text, image picker) does not fit the generated
 * forms. All writes go through App\Cms\PageWriter, like the API.
 *
 * Every page is resolved within the acting admin's active organisation, and
 * a page of another organisation is a 404 (as in GuestRegistrationController).
 *
 * Class PageController
 * @package App\Http\Controllers\Admin
 */
class PageController extends Controller
{
    /**
     * @var PageWriter
     */
    private $writer;

    /**
     * @param PageWriter $writer
     */
    public function __construct(PageWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Register the admin routes (inside the auth + admin group).
     */
    public static function routes()
    {
        $locales = implode('|', array_map('preg_quote', config('cms.locales')));

        \Route::get('pages', 'Admin\PageController@index');
        \Route::get('pages/create', 'Admin\PageController@create');
        \Route::post('pages', 'Admin\PageController@store');

        \Route::get('pages/{page}/edit/{locale}', 'Admin\PageController@edit')
            ->where([ 'page' => '[0-9]+', 'locale' => $locales ]);
        \Route::put('pages/{page}/edit/{locale}', 'Admin\PageController@update')
            ->where([ 'page' => '[0-9]+', 'locale' => $locales ]);

        \Route::get('pages/{page}/translations/{locale}/create', 'Admin\PageController@createTranslation')
            ->where([ 'page' => '[0-9]+', 'locale' => $locales ]);
        \Route::post('pages/{page}/translations/{locale}/copy', 'Admin\PageController@copyTranslation')
            ->where([ 'page' => '[0-9]+', 'locale' => $locales ]);
        \Route::delete('pages/{page}/translations/{locale}', 'Admin\PageController@destroyTranslation')
            ->where([ 'page' => '[0-9]+', 'locale' => $locales ]);

        \Route::delete('pages/{page}', 'Admin\PageController@destroy')->where('page', '[0-9]+');
        \Route::post('pages/{page}/home', 'Admin\PageController@setHome')->where('page', '[0-9]+');
    }

    /**
     * The page tree.
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('index', [ Page::class, $organisation ]);

        $pages = Page::forOrganisation($organisation)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.cms.pages.index', [
            'organisation' => $organisation,
            'rows' => $this->tree($pages),
            'locales' => config('cms.locales'),
            'defaultLocale' => config('cms.default_locale'),
        ]);
    }

    /**
     * Form for a new page, starting with its default-locale translation.
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('create', [ Page::class, $organisation ]);

        $page = new Page();
        $page->organisation()->associate($organisation);
        $page->setRelation('translations', new Collection());

        $translation = new PageTranslation();
        $translation->locale = config('cms.default_locale');
        $translation->blocks = [];

        return $this->form($organisation, $page, $translation);
    }

    /**
     * @param PageTranslationRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(PageTranslationRequest $request)
    {
        $organisation = $this->getActiveOrganisation();
        $this->authorize('create', [ Page::class, $organisation ]);

        $locale = config('cms.default_locale');

        $translation = $this->writer->createPage(
            $organisation,
            $request->pageAttributes(),
            $locale,
            $request->translationAttributes()
        );

        return $this->afterSave($request, $translation, 'De pagina is aangemaakt.');
    }

    /**
     * @param int $pageId
     * @param string $locale
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($pageId, $locale)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        $translation = $page->translation($locale);
        if (!$translation) {
            return redirect(action('Admin\PageController@createTranslation', [ $page->id, $locale ]));
        }

        return $this->form($page->organisation, $page, $translation);
    }

    /**
     * Save the page fields and the translation in $locale (creating it when
     * it does not exist yet).
     * @param PageTranslationRequest $request
     * @param int $pageId
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PageTranslationRequest $request, $pageId, $locale)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        $isNew = !$page->translation($locale);

        $translation = \DB::transaction(function () use ($request, $page, $locale) {
            $this->writer->updatePage($page, $request->pageAttributes());
            return $this->writer->saveTranslation($page, $locale, $request->translationAttributes());
        });

        return $this->afterSave(
            $request,
            $translation,
            $isNew ? 'De vertaling is aangemaakt.' : 'De pagina is opgeslagen.'
        );
    }

    /**
     * Form for a translation that does not exist yet.
     * @param int $pageId
     * @param string $locale
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function createTranslation($pageId, $locale)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        if ($page->translation($locale)) {
            return redirect(action('Admin\PageController@edit', [ $page->id, $locale ]));
        }

        $translation = new PageTranslation();
        $translation->locale = $locale;
        $translation->blocks = [];

        return $this->form($page->organisation, $page, $translation);
    }

    /**
     * Create the $locale translation as an unpublished copy of another one.
     * @param Request $request
     * @param int $pageId
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function copyTranslation(Request $request, $pageId, $locale)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        $this->validate($request, [
            'from' => [ 'required', 'string', 'in:' . implode(',', config('cms.locales')) ],
        ]);

        $this->writer->copyTranslation($page, $request->input('from'), $locale);

        return redirect(action('Admin\PageController@edit', [ $page->id, $locale ]))
            ->with('message', 'De vertaling is gekopieerd. Ze is nog niet gepubliceerd.');
    }

    /**
     * @param int $pageId
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyTranslation($pageId, $locale)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        $translation = $page->translation($locale);
        if (!$translation) {
            abort(404);
        }

        $this->writer->deleteTranslation($translation);

        return redirect(action('Admin\PageController@index'))
            ->with('message', 'De vertaling "' . $locale . '" is verwijderd.');
    }

    /**
     * @param int $pageId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($pageId)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('destroy', $page);

        $this->writer->deletePage($page);

        return redirect(action('Admin\PageController@index'))
            ->with('message', 'De pagina is verwijderd.');
    }

    /**
     * "Stel in als startpagina" (or, with `clear`, stop using it as such).
     * @param Request $request
     * @param int $pageId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setHome(Request $request, $pageId)
    {
        $page = $this->getPageInOrganisation($pageId);
        $this->authorize('edit', $page);

        $organisation = $page->organisation;

        if ($request->input('clear')) {
            if ((int) $organisation->home_page_id === (int) $page->id) {
                $this->writer->setHomePage($organisation, null);
            }
            $message = 'De organisatie heeft geen CMS-startpagina meer; / toont weer de kalender.';
        } else {
            $this->writer->setHomePage($organisation, $page);
            $message = 'Deze pagina is nu de startpagina.';
        }

        return redirect(action('Admin\PageController@index'))->with('message', $message);
    }

    /**
     * @param Request $request
     * @param PageTranslation $translation
     * @param string $message
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function afterSave(Request $request, PageTranslation $translation, string $message)
    {
        if ($request->input('preview')) {
            return redirect($translation->getUrl() . '?preview=1');
        }

        return redirect(action('Admin\PageController@edit', [ $translation->page_id, $translation->locale ]))
            ->with('message', $message);
    }

    /**
     * @param Organisation $organisation
     * @param Page $page
     * @param PageTranslation $translation
     * @return \Illuminate\Contracts\View\View
     */
    protected function form(Organisation $organisation, Page $page, PageTranslation $translation)
    {
        $translations = $page->exists ? $page->translations()->get()->keyBy('locale') : collect();

        $blocks = old('blocks', $translation->blocks);

        return view('admin.cms.pages.edit', [
            'organisation' => $organisation,
            'page' => $page,
            'translation' => $translation,
            'translations' => $translations,
            'locale' => $translation->locale,
            'locales' => config('cms.locales'),
            'defaultLocale' => config('cms.default_locale'),
            'blocks' => is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [],
            'blockTypes' => app(BlockRegistry::class)->all(),
            'parentOptions' => $this->parentOptions($organisation, $page),
            'isHome' => $page->exists && (int) $organisation->home_page_id === (int) $page->id,
        ]);
    }

    /**
     * Pages that can be the parent of $page (not itself or a descendant),
     * in tree order.
     * @param Organisation $organisation
     * @param Page $page
     * @return array[] [ 'page' => Page, 'depth' => int, 'title' => string ]
     */
    protected function parentOptions(Organisation $organisation, Page $page)
    {
        $pages = Page::forOrganisation($organisation)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $this->tree($pages);

        if (!$page->exists) {
            return $rows;
        }

        $out = [];
        $skipBelowDepth = null;
        foreach ($rows as $row) {
            if ($skipBelowDepth !== null) {
                if ($row['depth'] > $skipBelowDepth) {
                    continue;
                }
                $skipBelowDepth = null;
            }

            if ((int) $row['page']->id === (int) $page->id) {
                $skipBelowDepth = $row['depth'];
                continue;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Depth-first list of the pages with their depth. Pages whose parent is
     * gone (deleted) are shown at the top level.
     * @param Collection $pages
     * @return array[]
     */
    protected function tree(Collection $pages)
    {
        $ids = $pages->pluck('id')->all();
        $byParent = $pages->groupBy(function (Page $page) use ($ids) {
            return $page->parent_id && in_array($page->parent_id, $ids) ? $page->parent_id : 0;
        });

        $rows = [];
        $walk = function ($parentId, $depth) use (&$walk, &$rows, $byParent) {
            foreach ($byParent->get($parentId, []) as $page) {
                $rows[] = [
                    'page' => $page,
                    'depth' => $depth,
                    'title' => $this->pageTitle($page),
                ];
                if ($depth < 20) {
                    $walk($page->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);

        return $rows;
    }

    /**
     * The default-locale title, else the first translation's, else a placeholder.
     * @param Page $page
     * @return string
     */
    protected function pageTitle(Page $page)
    {
        $translation = $page->translation(config('cms.default_locale')) ?: $page->translations->first();

        return $translation ? $translation->title : ('Pagina #' . $page->id . ' (zonder vertaling)');
    }

    /**
     * @return Organisation
     */
    protected function getActiveOrganisation()
    {
        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation) {
            abort(404);
        }

        return $organisation;
    }

    /**
     * The page, or 404. Scoped to the acting admin's active organisation and
     * an admin role within it, like GuestRegistrationController.
     * @param int $pageId
     * @return Page
     */
    protected function getPageInOrganisation($pageId)
    {
        /** @var Page $page */
        $page = Page::findOrFail($pageId);

        $organisation = \Auth::user()->getActiveOrganisation();
        if (!$organisation
            || (int) $page->organisation_id !== (int) $organisation->id
            || !$organisation->isAdmin(\Auth::user())) {
            abort(404);
        }

        $page->setRelation('organisation', $organisation);

        return $page;
    }
}
