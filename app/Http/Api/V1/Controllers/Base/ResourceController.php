<?php

namespace App\Http\Api\V1\Controllers\Base;

use App\Http\Controllers\Controller;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\InputParsers\JsonBodyInputParser;
use CatLab\Charon\Laravel\InputParsers\PostInputParser;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Laravel\Processors\PaginationProcessor;
use CatLab\Charon\Pagination\PaginationBuilder;

/**
 * Class ResourceControllers
 * @package App\Http\Api\V1\Controllers\Base
 */
abstract class ResourceController extends Controller
{
    use \CatLab\Charon\Laravel\Controllers\ResourceController {
        getContext as traitGetContext;
    }

    /**
     * ResourceController constructor.
     * @param $resourceDefinitionClass
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function __construct($resourceDefinitionClass)
    {
        $this->setResourceDefinition(ResourceDefinitionLibrary::make($resourceDefinitionClass));
    }

    /**
     * @param string $action
     * @param array $parameters
     * @return Context|string
     */
    protected function getContext($action = Action::VIEW, $parameters = []) : \CatLab\Charon\Interfaces\Context
    {
        /** @var Context $context */
        $context = $this->traitGetContext($action, $parameters);

        $user = \Auth::getUser();
        if ($user) {
            $context->setParameter('currentUser', $user);
        }

        $context->addProcessor(new PaginationProcessor(PaginationBuilder::class));

        return $context;
    }

    /**
     * Set the input parsers that will be used to turn requests into resources.
     * @param \CatLab\Charon\Models\Context $context
     */
    protected function setInputParsers(\CatLab\Charon\Models\Context $context)
    {
        $context->addInputParser(JsonBodyInputParser::class);
        $context->addInputParser(PostInputParser::class);
    }
}
