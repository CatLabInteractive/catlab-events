<?php

namespace App\Http\Api\V1\Controllers;

use App\Events\OrderCancelled;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Organisation;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class OrdersController extends ResourceController
{
    const RESOURCE_DEFINITION = OrderResourceDefinition::class;
    const RESOURCE_ID = 'order';
    const PARENT_RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController;

    /**
     * @var Organisation
     */
    protected $organisation;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/orders',
            'orders',
            'OrderController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('orders');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->orders();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Organisation::findOrFail($parentId);
    }

    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    protected function afterSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew = false)
    {
        if ($isNew) {
            return;
        }

        if ($entity->wasChanged('ticket_category_id')) {
            // Ticket category was changed, so send email again.
            $entity->onConfirmation();
        }

        return $entity;
    }
}
