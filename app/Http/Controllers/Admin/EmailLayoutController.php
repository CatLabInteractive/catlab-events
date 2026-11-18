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

use App\Http\Controllers\Admin\Concerns\AdministersEmails;
use App\Models\EmailLayout;
use App\Services\Admin\Widgets\AccentColorWidget;
use App\Services\Admin\Widgets\LayoutHtmlWidget;
use App\Services\EmailTemplates;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\TextareaWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * The layout every mail of the organisation goes out in: the Charon edit
 * form of the EmailLayout api resource. An organisation has exactly one
 * layout, so there is no list and nothing to create or delete; the page is
 * the form.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailLayoutController extends BaseAdminController
{
    use AdministersEmails;

    public function __construct()
    {
        parent::__construct();

        $previewUrl = function () {
            return action('Admin\EmailLayoutController@preview');
        };

        $this->widgets()
            ->forField('accent_color', new AccentColorWidget())
            ->forField('footer_text', new TextareaWidget(false, [ 'rows' => 3, 'maxlength' => 2000 ]))
            ->forField('custom_html', new LayoutHtmlWidget($previewUrl));
    }

    /**
     * @param $path
     * @param $controller
     */
    public static function routes($path, $controller)
    {
        \Route::get($path, $controller . '@edit');
        \Route::put($path, $controller . '@update');

        // The form posts to the preview with its own method field (put).
        \Route::match([ 'get', 'post', 'put' ], $path . '/preview', $controller . '@preview');
    }

    /**
     * @return \App\Http\Api\V1\Controllers\EmailLayoutController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\EmailLayoutController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\EmailLayoutController::RESOURCE_ID;
    }

    /**
     * Always the layout of the active organisation.
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        return [
            \App\Http\Api\V1\Controllers\EmailLayoutController::RESOURCE_ID => $this->getAdministeredOrganisation()->id
        ];
    }

    /**
     * @param Request $request
     * @param null $id There is one layout; the url has no id.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function update(Request $request, $id = null)
    {
        return parent::update($request, $this->getAdministeredOrganisation()->id);
    }

    /**
     * The urls of this page carry no parameters.
     * @param $method
     * @param array $parameters
     * @return string
     */
    protected function action($method, $parameters = [])
    {
        return action($this->getRawControllerAction($method));
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function afterUpdate(Request $request, RESTResource $resource)
    {
        // What the active organisation had loaded is the layout of before.
        $this->getAdministeredOrganisation()->unsetRelation('emailLayout');

        Session::flash('message', 'De layout is opgeslagen.');

        return redirect(action('Admin\EmailLayoutController@edit'));
    }

    /**
     * @param JsonResponse $response
     * @param $redirectMethod
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleErrorResponse(JsonResponse $response, $redirectMethod)
    {
        return $this->refusedByApi($response);
    }

    /**
     * A sample mail in the stored layout (GET), or in the unsaved layout of
     * the form.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        $organisation = $this->getAdministeredOrganisation();
        $templates = app(EmailTemplates::class);

        $draft = null;
        if (!$request->isMethod('get')) {
            $api = $this->createApiController();

            $draft = new EmailLayout([
                'accent_color' => $api->blankToNull($this->draftField($request, 'accent_color')),
                'footer_text' => $api->blankToNull($this->draftField($request, 'footer_text')),
                'custom_html' => $api->normaliseHtml($this->draftField($request, 'custom_html')),
            ]);
        }

        $mail = $templates->render(
            $organisation,
            EmailTemplates::CONFIRMATION,
            $templates->getSampleAttributes($organisation, EmailTemplates::CONFIRMATION),
            null,
            $draft
        );

        return $this->sandboxedPreview($mail['body']);
    }

    /**
     * A settings page: no list to go back to.
     * @param Request $request
     * @param string $action
     * @param RESTResource|null $resource
     * @return array
     */
    protected function getPageChrome(Request $request, string $action, ?RESTResource $resource = null): array
    {
        return [
            'title' => 'Layout van de mails',
            'breadcrumbs' => [
                [ 'label' => 'E-mails', 'url' => action('Admin\EmailTemplateController@index') ],
                [ 'label' => 'Layout', 'url' => null ],
            ],
            'actions' => [],
        ];
    }

    /**
     * @param Request $request
     * @param RESTResource|null $resource
     * @return string|null
     */
    protected function getCancelUrl(Request $request, ?RESTResource $resource = null): ?string
    {
        return null;
    }

    /**
     * @param $action
     * @return string
     */
    protected function getView($action)
    {
        return $action === 'form' ? 'admin.emails.layout.form' : parent::getView($action);
    }
}
