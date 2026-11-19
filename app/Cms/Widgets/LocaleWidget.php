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

use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\ProvidesOptions;
use CatLab\CharonFrontend\Widgets\SelectWidget;
use Illuminate\Http\Request;

/**
 * A language of the CMS, limited to what makes sense on this form: the
 * languages a page does not have yet (for a new translation), or the ones
 * it has (to copy from). Without anything to choose, the field is left out.
 *
 * @package App\Cms\Widgets
 */
class LocaleWidget extends SelectWidget implements ProvidesOptions
{
    const NAMES = [ 'nl' => 'Nederlands', 'en' => 'Engels', 'fr' => 'Frans' ];

    /**
     * @var callable function (Request): string[]
     */
    private $locales;

    /**
     * @var string|null
     */
    private $emptyLabel;

    /**
     * @param callable $locales function (Request): string[] The locales to offer.
     * @param string|null $emptyLabel Label of a "none" option; null for no such option.
     */
    public function __construct(callable $locales, ?string $emptyLabel = null)
    {
        $this->locales = $locales;
        $this->emptyLabel = $emptyLabel;
    }

    /**
     * @param string $locale
     * @return string
     */
    public static function name(string $locale): string
    {
        return self::NAMES[$locale] ?? $locale;
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string[]
     */
    public function getOptions(Request $request, FormField $field): array
    {
        $options = [];
        foreach (call_user_func($this->locales, $request) as $locale) {
            $options[$locale] = self::name($locale);
        }

        if (count($options) && $this->emptyLabel !== null) {
            $options = [ '' => $this->emptyLabel ] + $options;
        }

        return $options;
    }

    /**
     * A new translation opened from a language tab (?locale=fr) starts on
     * that language.
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $data = parent::getViewData($field);

        $asked = request()->query('locale');
        if ($data['selected'] === '' && is_string($asked) && isset($field->getOptions()[$asked])) {
            $data['selected'] = $asked;
        }

        return $data;
    }

    /**
     * @param FormField $field
     * @return string
     */
    public function render(FormField $field): string
    {
        return count($field->getOptions()) ? parent::render($field) : '';
    }
}
