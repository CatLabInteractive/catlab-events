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
use App\Models\EmailTemplate;
use App\Services\Admin\Widgets\EmailContentWidget;
use App\Services\Admin\Widgets\EmailSubjectWidget;
use App\Services\EmailTemplates;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * The automatic mails ("Standaardmails") as the organisation sends them: a
 * Charon admin resource on top of the StandardMail api controller. There is
 * always one of each mail, so nothing is created; editing writes the
 * organisation's own version and deleting goes back to the standard text.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailTemplateController extends BaseAdminController
{
    use AdministersEmails;

    public function __construct()
    {
        parent::__construct();

        $this->widgets()
            ->forField('subject', new EmailSubjectWidget([ 'required' => 'required' ]))
            ->forField('content', new EmailContentWidget(function (FormField $field, string $type) {
                return action('Admin\EmailTemplateController@preview', [ 'type' => $type ]);
            }));
    }

    /**
     * @param $path
     * @param $controller
     */
    public static function routes($path, $controller)
    {
        $types = EmailTemplates::routePattern();

        \Route::get($path, $controller . '@index');
        \Route::get($path . '/{type}', $controller . '@show')->where('type', $types);
        \Route::put($path . '/{type}', $controller . '@update')->where('type', $types);
        \Route::delete($path . '/{type}', $controller . '@destroy')->where('type', $types);
        \Route::get($path . '/{type}/edit', $controller . '@edit')->where('type', $types);
        \Route::get($path . '/{type}/delete', $controller . '@confirmDelete')->where('type', $types);

        // The form posts to the preview with its own method field (put).
        \Route::match([ 'get', 'post', 'put' ], $path . '/{type}/preview', $controller . '@preview')->where('type', $types);
    }

    /**
     * @return \App\Http\Api\V1\Controllers\StandardMailController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\StandardMailController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'type';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\StandardMailController::RESOURCE_ID;
    }

    /**
     * The mails of the active organisation.
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        return [
            'organisation' => $this->getAdministeredOrganisation()->id
        ];
    }

    /**
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        return $method === Action::INDEX ? [ $this->getAdministeredOrganisation() ] : null;
    }

    /**
     * There is one of each mail; none are added.
     * @param Request $request
     * @param ResourceDefinition $resourceDefinition
     * @return bool
     */
    protected function canCreateModel(Request $request, ResourceDefinition $resourceDefinition)
    {
        return false;
    }

    /**
     * A mail opens in its editor, which shows the preview as well.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show(Request $request)
    {
        $this->getAdministeredOrganisation();

        return redirect($this->action('edit'));
    }

    /**
     * @param Request $request
     * @param $model
     * @return bool
     */
    protected function canViewModel(Request $request, $model)
    {
        return false;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function confirmDelete(Request $request)
    {
        return parent::confirmDelete($request)->with(
            'question',
            'De aangepaste versie wordt verwijderd en de standaardversie wordt weer verstuurd. Doorgaan?'
        );
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function destroy(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, $request)->getResource();
        $response = $this->dispatchToApi(Action::DESTROY, $request);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            return $this->handleErrorResponse($response, 'destroy');
        }

        Session::flash('message', 'De mail "' . $this->getResourceTitle($request, $resource) . '" is terug de standaardversie.');

        return redirect(action('Admin\EmailTemplateController@index'));
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function afterUpdate(Request $request, RESTResource $resource)
    {
        Session::flash('message', 'De mail "' . $this->getResourceTitle($request, $resource) . '" is aangepast.');

        return redirect(action('Admin\EmailTemplateController@index'));
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
     * Just the mail body, for the preview frame, with made-up data: the
     * version that goes out now (GET) or what is in the form, without
     * saving it.
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $type)
    {
        $organisation = $this->getAdministeredOrganisation();
        $templates = app(EmailTemplates::class);

        $draft = null;
        if (!$request->isMethod('get')) {
            $draft = new EmailTemplate([
                'type' => $type,
                'subject' => $this->draftField($request, 'subject'),
                'content' => $this->draftField($request, 'content'),
            ]);
        }

        $mail = $templates->render(
            $organisation,
            $type,
            $templates->getSampleAttributes($organisation, $type),
            $draft
        );

        return $this->sandboxedPreview($mail['body']);
    }

    /**
     * @param $action
     * @return string
     */
    protected function getView($action)
    {
        switch ($action) {
            case 'index':
                return 'admin.emails.standard.index';

            case 'form':
                return 'admin.emails.standard.form';

            case Action::DESTROY:
                return 'admin.emails.confirm-reset';
        }

        return parent::getView($action);
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getResourceTitle(Request $request, RESTResource $resource): string
    {
        return (string) ($resource->toArray()['label'] ?? '');
    }

    protected function getEntityLabel(): string
    {
        return 'mail';
    }

    protected function getIndexTitle(): string
    {
        return 'E-mails';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getEditActionLabel(ResourceDefinition $resourceDefinition)
    {
        return 'Aanpassen';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getDestroyActionLabel(ResourceDefinition $resourceDefinition)
    {
        return 'Terug naar standaard';
    }
}
