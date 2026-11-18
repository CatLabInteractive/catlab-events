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

namespace App\Services\Admin\Widgets;

use App\Services\EmailTemplates;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\ProvidesOptions;
use CatLab\CharonFrontend\Widgets\SelectWidget;
use Illuminate\Http\Request;

/**
 * The mail a new template is for. Choosing another one reloads the form
 * (?type=...) with the standard text of that mail.
 *
 * @package App\Services\Admin\Widgets
 */
class EmailTypeWidget extends SelectWidget implements ProvidesOptions
{
    protected $view = 'admin.emails.widgets.type';

    /**
     * The mail type a form is about: the template's own, else the one in
     * the url, else the first.
     * @param FormField $field
     * @return string
     */
    public static function typeOf(FormField $field): string
    {
        $resource = $field->getResource();

        // A resource is about its own mail, whichever that is.
        if ($resource) {
            $type = $resource->toArray()['type'] ?? null;
            if (is_string($type) && app(EmailTemplates::class)->exists($type)) {
                return $type;
            }
        }

        $type = request()->query('type');

        return is_string($type) && in_array($type, EmailTemplates::EVENT_TYPES, true)
            ? $type
            : EmailTemplates::CONFIRMATION;
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string[]
     */
    public function getOptions(Request $request, FormField $field): array
    {
        $templates = app(EmailTemplates::class);

        $options = [];
        foreach (EmailTemplates::EVENT_TYPES as $type) {
            $options[$type] = $templates->getLabel($type);
        }

        return $options;
    }

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        return [
            'selected' => self::typeOf($field),
            'reloadUrl' => url()->current(),
        ];
    }
}
