<?php

namespace App\Http\Controllers;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\Request;

/**
 * Class ScoreController
 * @package App\Http\Controllers
 */
class ScoreController implements FrontCrudControllerContract
{

    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @return Table
     */
    public function getTableForResourceCollection(
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        Context $context
    ): Table
    {
        $table = new Table(
            $collection,
            $resourceDefinition,
            $context,
            $request->getRequestUri()
        );

        return $table;
    }
}
