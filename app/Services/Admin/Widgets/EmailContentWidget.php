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
use App\Services\MailHtml;
use CatLab\CharonFrontend\Widgets\BaseWidget;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;

/**
 * The content of a mail template: the editor (formatted or as HTML), the
 * placeholders of its mail, and a sandboxed preview that the "Voorbeeld"
 * button refreshes with what is in the form, without saving it. A new
 * template starts from the standard text of its mail.
 *
 * @package App\Services\Admin\Widgets
 */
class EmailContentWidget extends BaseWidget
{
    protected $view = 'admin.emails.widgets.content';

    /**
     * @var callable function (FormField, string $type): string
     */
    private $previewUrl;

    /**
     * @var string
     */
    private $previewCaption;

    /**
     * @param callable $previewUrl function (FormField, string $type): string Where the preview is rendered (GET: as stored, POST: the form).
     * @param string $previewCaption What the preview shows, next to its button.
     */
    public function __construct(
        callable $previewUrl,
        string $previewCaption = 'Met verzonnen gegevens. Toont je wijzigingen zonder ze op te slaan.'
    ) {
        $this->previewUrl = $previewUrl;
        $this->previewCaption = $previewCaption;
    }

    /**
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        $templates = app(EmailTemplates::class);
        $type = EmailTypeWidget::typeOf($field);

        $content = $field->old('', $field->getValue());
        if (!$field->getResource() && !$field->hasOldInput()) {
            $content = $templates->getDefaultContent($type);
        }

        return [
            // Stored before mails were cleaned on save, or typed in a refused
            // form: never put it in the page as it is.
            'content' => is_scalar($content) ? (string) MailHtml::clean((string) $content) : '',
            'placeholders' => $templates->getPlaceholders($type),
            'previewAction' => call_user_func($this->previewUrl, $field, $type),
            'previewCaption' => $this->previewCaption,
        ];
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @return string|null
     */
    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);

        return is_string($value) ? $this->nullIfEmpty($value) : null;
    }
}
