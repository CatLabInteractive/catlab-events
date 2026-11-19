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
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Widgets\TextWidget;
use Illuminate\Http\Request;

/**
 * "Domeinen": the domains the active organisation's site answers on, and
 * which one is canonical.
 *
 * Class DomainController
 * @package App\Http\Controllers\Admin
 */
class DomainController extends BaseAdminController
{
    use AdministersEmails;

    public function __construct()
    {
        parent::__construct();

        $this->widgets()
            ->forField('domain', new TextWidget([ 'maxlength' => 253, 'required' => 'required', 'placeholder' => 'www.quizfabriek.be' ]));
    }

    /**
     * @return \App\Http\Api\V1\Controllers\OrganisationDomainController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\OrganisationDomainController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\OrganisationDomainController::RESOURCE_ID;
    }

    /**
     * Listed and created within the active organisation; one domain only
     * when it is one of the active organisation's.
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

        $known = $this->getAdministeredOrganisation()
            ->domains()
            ->whereKey($request->route(static::getRouteIdParameterName()))
            ->exists();

        if (!$known) {
            abort(404);
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
     * By domain, unless the list is sorted otherwise.
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $this->getAdministeredOrganisation();

        if (!$request->query->has('sort')) {
            $request->query->set('sort', 'domain');
        }

        return parent::index($request);
    }

    /**
     * A domain opens in its form.
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
     * Back to the list after saving.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterSave(Request $request, RESTResource $resource)
    {
        return redirect(action('Admin\DomainController@index'));
    }

    protected function getEntityLabel(): string
    {
        return 'domein';
    }

    protected function getIndexTitle(): string
    {
        return 'Domeinen';
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
