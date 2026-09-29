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

use App\Cms\CmsAssets;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\AssetResourceDefinition;
use App\Models\Organisation;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Image upload for an organisation, so an API client can create CMS pages
 * with images end to end (image_id / og_image_id reference the returned
 * asset id). Same validation and storage as the admin editor's upload.
 *
 * Class AssetController
 * @package App\Http\Api\V1\Controllers
 */
class AssetController extends ResourceController
{
    const RESOURCE_DEFINITION = AssetResourceDefinition::class;

    use \CatLab\Charon\Laravel\Controllers\ResourceController;

    /**
     * AssetController constructor.
     */
    public function __construct()
    {
        parent::__construct(static::RESOURCE_DEFINITION);
    }

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->post('organisations/{organisation}/assets', 'AssetController@upload')
            ->tag('assets')
            ->summary('Upload an image (multipart/form-data, field "file"; jpeg, png, gif or webp, max 10 MB) for the organisation.')
            ->consumes('multipart/form-data')
            ->returns()->one(AssetResourceDefinition::class)
            ->parameters()->path('organisation')->int()->required()->describe('Organisation id');
        // No ->file('file') parameter: Charon's Swagger builder cannot describe
        // file parameters; the summary documents the multipart field.
    }

    /**
     * @param Request $request
     * @param int $organisationId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function upload(Request $request, $organisationId)
    {
        /** @var Organisation $organisation */
        $organisation = Organisation::findOrFail($organisationId);

        $user = \Auth::user();
        if (!$user || !$organisation->isAdmin($user)) {
            abort(403, 'Only organisation admins can upload assets.');
        }

        $validator = Validator::make($request->all(), CmsAssets::UPLOAD_RULES);
        if ($validator->fails()) {
            return $this->toResponse([
                'error' => [
                    'message' => 'Could not decode resource.',
                    'issues' => $validator->errors()->toArray(),
                ]
            ])->setStatusCode(422);
        }

        $asset = app(CmsAssets::class)->store($request->file('file'), $organisation, $user);

        $context = $this->getContext(Action::VIEW);

        return $this->getResourceResponse($this->toResource($asset, $context), $context)
            ->setStatusCode(201);
    }
}
