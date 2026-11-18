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
use App\Services\Admin\Widgets\EmailTypeWidget;
use App\Services\EmailTemplates;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\TextWidget;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * The library of named mail templates ("Sjablonen") that events can choose
 * instead of the standard mail: a Charon admin resource on top of the
 * EmailTemplate api controller, with the mail editor (placeholders, preview)
 * as the widget of its content.
 *
 * @package App\Http\Controllers\Admin
 */
class EmailLibraryController extends BaseAdminController
{
    use AdministersEmails;

    public function __construct()
    {
        parent::__construct();

        $this->widgets()
            ->forField('type', new EmailTypeWidget())
            ->forField('name', new TextWidget([ 'maxlength' => 100, 'required' => 'required' ]))
            ->forField('subject', new EmailSubjectWidget([ 'required' => 'required' ]))
            ->forField('content', new EmailContentWidget(function (FormField $field, string $type) {
                $resource = $field->getResource();

                return $resource
                    ? action('Admin\EmailLibraryController@preview', [ 'id' => $resource->getIdentifiers()->getValues()[0]->getValue() ])
                    : action('Admin\EmailLibraryController@previewNew', [ 'type' => $type ]);
            }));
    }

    /**
     * @param $path
     * @param $controller
     */
    public static function routes($path, $controller)
    {
        // The form posts to the preview with its own method field (put on edit).
        \Route::match([ 'get', 'post', 'put' ], $path . '/create/preview', $controller . '@previewNew');
        \Route::match([ 'get', 'post', 'put' ], $path . '/{id}/preview', $controller . '@preview');

        static::traitRoutes($path, $controller);
    }

    /**
     * @return \App\Http\Api\V1\Controllers\EmailTemplateController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\EmailTemplateController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\EmailTemplateController::RESOURCE_ID;
    }

    /**
     * Listed and created within the active organisation.
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    'organisation' => $this->getAdministeredOrganisation()->id
                ];
        }

        return null;
    }

    /**
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    $this->getAdministeredOrganisation()
                ];
        }

        return null;
    }

    /**
     * By name, unless the list is sorted otherwise.
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $this->getAdministeredOrganisation();

        return parent::index($request);
    }

    /**
     * "Gebruikt door" links each event to its e-mails page.
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function getTableForResourceCollection(
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        foreach ($collection as $resource) {
            $template = $resource->getSource();
            if (!$template instanceof EmailTemplate) {
                continue;
            }

            $links = [];
            foreach ($template->getEventNames() as $eventId => $name) {
                $links[] = '<a href="' . e(action('Admin\EventEmailController@index', [ $eventId ])) . '">' . e($name) . '</a>';
            }

            foreach ($resource->getProperties()->getResourceFields()->getValues() as $property) {
                if ($property->getField()->getDisplayName() === 'used_by') {
                    $property->setValue(new HtmlString(implode(', ', $links)));
                }
            }
        }

        return parent::getTableForResourceCollection($request, $collection, $resourceDefinition, $context);
    }

    /**
     * A template opens in its editor.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show(Request $request)
    {
        return redirect($this->action('edit'));
    }

    /**
     * No "view" next to "edit" in the list.
     * @param Request $request
     * @param $model
     * @return bool
     */
    protected function canViewModel(Request $request, $model)
    {
        return false;
    }

    /**
     * A template an event still uses is refused: say so instead of "deleted".
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

        return $this->afterDestroy($request, $resource);
    }

    /**
     * The api's validation messages, without its generic English first line.
     * @param JsonResponse $response
     * @param $redirectMethod
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleErrorResponse(JsonResponse $response, $redirectMethod)
    {
        return $this->refusedByApi($response);
    }

    /**
     * Back to the list after saving.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterSave(Request $request, RESTResource $resource)
    {
        return redirect(action('Admin\EmailLibraryController@index'));
    }

    /**
     * The mail as it would go out, with made-up data: the stored template
     * (GET) or what is in the form (POST), without saving it.
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $id)
    {
        $template = $this->getAdministeredOrganisation()
            ->emailTemplates()
            ->library()
            ->findOrFail($id);

        if (!$request->isMethod('get')) {
            $template = $this->draft($request, $template->type, $template->subject, $template->content);
        }

        return $this->renderPreview($template);
    }

    /**
     * The preview of a template that isn't saved yet: the standard text of
     * the mail (GET) or what is in the form (POST).
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function previewNew(Request $request)
    {
        $this->getAdministeredOrganisation();

        $type = $request->input('type');
        if (!is_string($type) || !in_array($type, EmailTemplates::EVENT_TYPES, true)) {
            abort(404);
        }

        $templates = app(EmailTemplates::class);

        return $this->renderPreview($request->isMethod('get')
            ? new EmailTemplate([
                'type' => $type,
                'subject' => $templates->getDefaultSubject($type),
                'content' => $templates->getDefaultContent($type),
            ])
            : $this->draft($request, $type, '', ''));
    }

    /**
     * An unsaved template from what the form posts (fields[subject], fields[content]).
     * @param Request $request
     * @param string $type
     * @param string $subject
     * @param string $content
     * @return EmailTemplate
     */
    private function draft(Request $request, $type, $subject, $content)
    {
        return new EmailTemplate([
            'type' => $type,
            'subject' => $this->draftField($request, 'subject'),
            'content' => $this->draftField($request, 'content'),
        ]);
    }

    /**
     * @param EmailTemplate $template
     * @return \Illuminate\Http\Response
     */
    private function renderPreview(EmailTemplate $template)
    {
        $organisation = $this->getAdministeredOrganisation();
        $templates = app(EmailTemplates::class);

        $mail = $templates->render(
            $organisation,
            $template->type,
            $templates->getSampleAttributes($organisation, $template->type),
            $template
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
                return 'admin.emails.library.index';

            case 'form':
                return 'admin.emails.library.form';
        }

        return parent::getView($action);
    }

    protected function getEntityLabel(): string
    {
        return 'sjabloon';
    }

    protected function getIndexTitle(): string
    {
        return 'Sjablonen';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getCreateActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.create_entity', [ 'entity' => $this->getEntityLabel() ]);
    }
}
