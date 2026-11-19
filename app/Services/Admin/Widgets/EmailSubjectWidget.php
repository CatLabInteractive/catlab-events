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
use CatLab\CharonFrontend\Widgets\TextWidget;

/**
 * The subject of a mail template; a new template starts from the standard
 * subject of its mail.
 *
 * @package App\Services\Admin\Widgets
 */
class EmailSubjectWidget extends TextWidget
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes + [ 'maxlength' => 255 ]);
    }

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $data = parent::getViewData($field);

        if (!$field->getResource() && !$field->hasOldInput()) {
            $data['values'] = [ app(EmailTemplates::class)->getDefaultSubject(EmailTypeWidget::typeOf($field)) ];
        }

        return $data;
    }
}
