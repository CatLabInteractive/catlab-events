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

use App\Http\Controllers\Controller;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Laravel\InputParsers\JsonBodyInputParser;
use CatLab\Charon\Laravel\InputParsers\PostInputParser;
use CatLab\Charon\OpenApi\V2\OpenApiV2Builder;
use CatLab\Charon\Swagger\Authentication\OAuth2Authentication;
use CatLab\Charon\Swagger\SwaggerBuilder;

use Request;

/**
 * Class DescriptionController
 * @package App\Http\Api\V1\Controllers
 */
class DescriptionController extends Controller
{
    use \CatLab\Charon\Laravel\Controllers\ResourceController;

    /**
     * @return RouteCollection
     */
    public function getRouteCollection() : RouteCollection
    {
        $routes = include __DIR__ . '/../routes.php';
        return $routes;
    }

    /**
     * @param $format
     * @return \Illuminate\Http\Response
     */
    public function description($format)
    {
        switch ($format) {
            case 'txt':
            case 'text':
                return $this->textResponse();
                break;

            case 'json':
                return $this->swaggerResponse();
                break;
        }
    }

    /**
     * @return \Illuminate\Http\Response
     */
    protected function textResponse()
    {
        $routes = $this->getRouteCollection();
        return \Response::make($routes->__toString(), 200, [ 'Content-type' => 'text/text' ]);
    }

    /**
     * @return mixed
     */
    protected function swaggerResponse()
    {
        $builder = new OpenApiV2Builder(Request::getHttpHost(), '/');

        $builder
            ->setTitle('CatLab Events')
            ->setDescription('CatLab Events API')
            ->setContact('CatLab Interactive', 'http://www.catlab.eu/', 'thijs@catlab.be')
            ->setVersion('1.0');

        /*
        $oauth2 = new OAuth2Authentication('oauth2');
        $oauth2
            ->setAuthorizationUrl(url('oauth/authorize'))
            ->setFlow('implicit')
            ->addScope('full', 'Full access')
        ;

        $builder->addAuthentication($oauth2);
        */

        foreach ($this->getRouteCollection()->getRoutes() as $route) {
            $builder->addRoute($route);
        }

        return $builder->build($this->getContext());
    }

    /**
     * Set the input parsers that will be used to turn requests into resources.
     * @param \CatLab\Charon\Models\Context $context
     */
    protected function setInputParsers(\CatLab\Charon\Models\Context $context)
    {
        $context->addInputParser(JsonBodyInputParser::class);

        // Don't include PostInputParser.
        // $context->addInputParser(PostInputParser::class);
    }
}
