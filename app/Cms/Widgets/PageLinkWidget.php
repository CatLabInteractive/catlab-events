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

namespace App\Cms\Widgets;

use App\Models\Page;
use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * What a page is (pages.link): a page with content, a menu link to one of
 * the fixed parts of the site (@series, @calendar, @blog), or a menu link
 * to another address.
 *
 * @package App\Cms\Widgets
 */
class PageLinkWidget extends BaseWidget
{
    const KIND_URL = 'url';

    const KINDS = [
        '' => 'Pagina met inhoud',
        Page::LINK_SERIES => 'Menulink: de quizreeksen',
        Page::LINK_CALENDAR => 'Menulink: de kalender',
        Page::LINK_BLOG => 'Menulink: de blog',
        self::KIND_URL => 'Menulink: een ander adres',
    ];

    protected $view = 'admin.cms.widgets.page-link';

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        if ($field->hasOldInput()) {
            $kind = (string) $field->old('kind', '');
            $url = (string) $field->old('url', '');
        } else {
            $value = (string) $field->getValue();
            $kind = in_array($value, Page::FIXED_LINKS, true) ? $value : ($value !== '' ? self::KIND_URL : '');
            $url = $kind === self::KIND_URL ? $value : '';
        }

        return [
            'kinds' => self::KINDS,
            'kind' => isset(self::KINDS[$kind]) ? $kind : '',
            'url' => $url,
        ];
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string|null
     */
    public function read(Request $request, FormField $field)
    {
        $kind = $this->input($request, $field, 'kind');

        if ($kind === self::KIND_URL) {
            $url = $this->input($request, $field, 'url');
            return is_string($url) ? $this->nullIfEmpty(trim($url)) : null;
        }

        return in_array($kind, Page::FIXED_LINKS, true) ? $kind : null;
    }
}
