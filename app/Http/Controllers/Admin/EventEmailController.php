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
use App\Models\Event;
use App\Models\EventMail;
use App\Services\Admin\Widgets\EmailContentWidget;
use App\Services\Admin\Widgets\EmailSubjectWidget;
use App\Services\Admin\Widgets\EventTemplateWidget;
use App\Services\EmailTemplates;
use App\Services\MailSource;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * The mails of one event (the "E-mails" tab of the event): a Charon admin
 * resource on top of the EventMail api controller. An event follows its
 * organisation until it chooses a library template or writes an own text;
 * deleting that choice goes back to the organisation's version.
 *
 * @package App\Http\Controllers\Admin
 */
class EventEmailController extends BaseAdminController
{
    use AdministersEmails;

    public function __construct()
    {
        parent::__construct();

        $this->setParentController(EventController::class, 'event');

        $this->widgets()
            ->forField('email_template_id', new EventTemplateWidget())
            ->forField('subject', new EmailSubjectWidget())
            ->forField('content', new EmailContentWidget(function (FormField $field, string $type) {
                return action('Admin\EventEmailController@preview', [
                    'event' => request()->route('event'),
                    'type' => $type,
                ]);
            }, 'Met de gegevens van dit evenement en een verzonnen speler. Toont je eigen tekst zonder hem op te slaan.'));
    }

    /**
     * @param $path
     * @param $controller
     */
    public static function routes($path, $controller)
    {
        $types = EmailTemplates::routePattern(EmailTemplates::EVENT_TYPES);

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
     * @return \App\Http\Api\V1\Controllers\EventMailController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\EventMailController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'type';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\EventMailController::RESOURCE_ID;
    }

    /**
     * The event of the url, when it belongs to the active organisation and
     * the user administers that; a 404 otherwise.
     * @param Request $request
     * @return Event
     */
    protected function getEvent(Request $request)
    {
        /** @var Event $event */
        $event = Event::findOrFail($request->route('event'));

        if ((int) $event->organisation_id !== (int) $this->getAdministeredOrganisation()->id) {
            abort(404);
        }

        return $event;
    }

    /**
     * Every call to the api is about an event of the active organisation.
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        return [
            'event' => $this->getEvent($request)->id
        ];
    }

    /**
     * The urls of the list are those of this event.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'event' => $request->route('event')
        ];
    }

    /**
     * @param Request $request
     * @param $method
     * @return array|null
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        return $method === Action::INDEX ? [ $this->getEvent($request) ] : null;
    }

    /**
     * An event has one of each of its mails; none are added.
     * @param Request $request
     * @param ResourceDefinition $resourceDefinition
     * @return bool
     */
    protected function canCreateModel(Request $request, ResourceDefinition $resourceDefinition)
    {
        return false;
    }

    /**
     * The list is the "E-mails" tab of the event.
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $event = $this->getEvent($request);

        return parent::index($request)->with([
            'chrome' => null,
            'hub' => (new EventController())->getHubChrome($request, $event->id, 'emails'),
            'event' => $event,
        ]);
    }

    /**
     * A mail the event doesn't send (any more) only shows up while it still
     * has a choice, and says so.
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
            $mail = $resource->getSource();
            if (!$mail instanceof EventMail || $mail->isApplicable()) {
                continue;
            }

            foreach ($resource->getProperties()->getResourceFields()->getValues() as $property) {
                if ($property->getField()->getDisplayName() === 'version') {
                    $property->setValue($property->getValue() . '. Dit evenement verstuurt deze mail niet. Je kan de aanpassing verwijderen.');
                }
            }
        }

        return parent::getTableForResourceCollection($request, $collection, $resourceDefinition, $context);
    }

    /**
     * A mail opens in its editor, which shows the preview as well.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show(Request $request)
    {
        $this->getEvent($request);

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
     * The choice for a mail the event doesn't send can only be removed.
     * @param Request $request
     * @param $model
     * @return bool
     */
    protected function canEditModel(Request $request, $model)
    {
        $source = $model instanceof RESTResource ? $model->getSource() : $model;
        if ($source instanceof EventMail && !$source->isApplicable()) {
            return false;
        }

        return parent::canEditModel($request, $model);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function confirmDelete(Request $request)
    {
        return parent::confirmDelete($request)->with(
            'question',
            'De keuze van dit evenement (een sjabloon of een eigen tekst) gaat verloren. Dit evenement verstuurt weer de standaard van de organisatie. Doorgaan?'
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

        Session::flash('message', 'De mail "' . $this->getResourceTitle($request, $resource) . '" gebruikt weer de standaard van de organisatie.');

        return redirect($this->indexUrl($request));
    }

    /**
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function afterUpdate(Request $request, RESTResource $resource)
    {
        $label = $this->getResourceTitle($request, $resource);

        Session::flash('message', $resource->getSource() instanceof EventMail && $resource->getSource()->email_template_id
            ? 'De mail "' . $label . '" gebruikt nu een sjabloon voor dit evenement.'
            : 'De mail "' . $label . '" heeft nu een eigen tekst voor dit evenement.');

        return redirect($this->indexUrl($request));
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
     * Back to the list of the event, whatever page the form was opened from.
     * @param Request $request
     * @param RESTResource|null $resource
     * @return string|null
     */
    protected function getCancelUrl(Request $request, ?RESTResource $resource = null): ?string
    {
        return $this->indexUrl($request);
    }

    /**
     * @param Request $request
     * @return string
     */
    private function indexUrl(Request $request)
    {
        return action('Admin\EventEmailController@index', [ 'event' => $request->route('event') ]);
    }

    /**
     * The mail with the real event. A GET shows the version in use, the
     * form its own text, without saving it.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        $event = $this->getEvent($request);
        $type = $request->route('type');

        if (!EventMail::of($event, $type)) {
            abort(404);
        }

        $organisation = $event->organisation;
        $templates = app(EmailTemplates::class);

        // null: what resolve() picks for the event.
        $source = null;
        if (!$request->isMethod('get')) {
            $source = new MailSource(
                MailSource::EVENT,
                $this->draftField($request, 'subject'),
                $this->draftField($request, 'content')
            );
        }

        $mail = $templates->render(
            $organisation,
            $type,
            $templates->getSampleAttributes($organisation, $type, $event),
            $source
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
                return 'admin.events.emails.index';

            case 'form':
                return 'admin.events.emails.form';

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
