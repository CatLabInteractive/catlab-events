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

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\Controllers\Cms\CmsApiValidation;
use App\Http\Api\V1\ResourceDefinitions\EmailLayoutResourceDefinition;
use App\Models\EmailLayout;
use App\Models\Organisation;
use App\Services\EmailLayouts;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The mail layout of an organisation. There is exactly one: it is read and
 * written, never created or deleted.
 *
 * @package App\Http\Api\V1\Controllers
 */
class EmailLayoutController extends ResourceController
{
    const RESOURCE_DEFINITION = EmailLayoutResourceDefinition::class;
    const RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\CrudController {
        edit as traitEdit;
        patch as traitPatch;
    }
    use CmsApiValidation;

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $path = 'organisations/{' . self::RESOURCE_ID . '}/emailLayout';

        $routes->get($path, 'EmailLayoutController@view')
            ->tag('emails')
            ->summary('The mail layout of the organisation.')
            ->returns()->one(static::RESOURCE_DEFINITION)
            ->parameters()->path(self::RESOURCE_ID)->int()->required()->describe('Organisation id');

        $routes->put($path, 'EmailLayoutController@edit')
            ->tag('emails')
            ->summary('Set the mail layout of the organisation.')
            ->returns()->one(static::RESOURCE_DEFINITION)
            ->parameters()->resource(static::RESOURCE_DEFINITION)->required()
            ->parameters()->path(self::RESOURCE_ID)->int()->required()->describe('Organisation id');

        $routes->patch($path, 'EmailLayoutController@patch')
            ->tag('emails')
            ->summary('Change some fields of the mail layout of the organisation.')
            ->returns()->one(static::RESOURCE_DEFINITION)
            ->parameters()->resource(static::RESOURCE_DEFINITION)->required()
            ->parameters()->path(self::RESOURCE_ID)->int()->required()->describe('Organisation id');
    }

    /**
     * The stored layout, or a new one for an organisation without.
     * @param Request $request
     * @return EmailLayout
     */
    protected function findEntity(Request $request)
    {
        $organisation = Organisation::findOrFail($request->route(self::RESOURCE_ID));

        $layout = $organisation->emailLayout()->first() ?: new EmailLayout();
        $layout->organisation()->associate($organisation);
        $layout->syncOriginalAttribute('organisation_id');

        return $layout;
    }

    /**
     * @param Request $request
     * @param Model $entity
     * @return Model
     * @throws \CatLab\Requirements\Exceptions\ResourceValidationException
     */
    protected function saveEntity(Request $request, Model $entity)
    {
        /** @var EmailLayout $entity */
        return $this->throughWriter(function () use ($entity) {
            $entity->accent_color = $this->blankToNull($entity->accent_color);
            $entity->footer_text = $this->blankToNull($entity->footer_text);
            $entity->custom_html = $this->normaliseHtml($entity->custom_html);

            Validator::make([
                'accent_color' => $entity->accent_color,
                'footer_text' => $entity->footer_text,
            ], [
                'accent_color' => [ 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/' ],
                'footer_text' => 'nullable|string|max:2000',
            ], [
                'accent_color.regex' => 'De accentkleur moet een kleur zijn zoals #4cb050.'
            ], [
                'footer_text' => 'voettekst',
            ])->validate();

            // Every occurrence gets filled, so the raw HTML must hold exactly one as well.
            $html = $entity->custom_html;
            if ($html !== null && (
                preg_match_all('/\{\{\s*content\s*\}\}/', $html) !== 1
                || preg_match_all('/\{\{\s*content\s*\}\}/', $this->withoutInertBlocks($html)) !== 1
            )) {
                throw ValidationException::withMessages([
                    'custom_html' => 'De layout moet precies één keer {{ content }} bevatten.'
                ]);
            }

            $entity->save();

            return $entity;
        });
    }

    /**
     * Empty, or the same as the default, is null: the organisation then
     * follows later changes to the default.
     * @param mixed $html
     * @return string|null
     */
    public function normaliseHtml($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return null;
        }

        // Browsers post line breaks as CRLF.
        $normalised = str_replace("\r\n", "\n", $html);
        if (trim($normalised) === trim(app(EmailLayouts::class)->getDefaultHtml())) {
            return null;
        }

        return $normalised;
    }

    /**
     * A copy of the HTML without the parts that never show up in the mail
     * body: comments, style blocks and the head.
     * @param string $html
     * @return string
     */
    private function withoutInertBlocks($html)
    {
        return preg_replace([
            '/<!--.*?-->/s',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<head\b[^>]*>.*?<\/head>/is'
        ], '', $html);
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    public function blankToNull($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit(Request $request)
    {
        return $this->answerMalformedInput(function () use ($request) {
            return $this->traitEdit($request);
        });
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function patch(Request $request)
    {
        return $this->answerMalformedInput(function () use ($request) {
            return $this->traitPatch($request);
        });
    }
}
