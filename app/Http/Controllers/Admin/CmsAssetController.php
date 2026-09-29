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

use App\Cms\CmsAssets;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Images for the CMS editor: the upload endpoint of TinyMCE and the block
 * image picker, and the picker's list of the active organisation's images.
 * Both answer JSON.
 *
 * Class CmsAssetController
 * @package App\Http\Controllers\Admin
 */
class CmsAssetController extends Controller
{
    /**
     * @var CmsAssets
     */
    private $assets;

    /**
     * @param CmsAssets $assets
     */
    public function __construct(CmsAssets $assets)
    {
        $this->assets = $assets;
    }

    /**
     * Register the admin routes (inside the auth + admin group).
     */
    public static function routes()
    {
        \Route::post('cms/upload', 'Admin\CmsAssetController@upload');
        \Route::get('cms/assets', 'Admin\CmsAssetController@index');
    }

    /**
     * GET admin/cms/assets?q=
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $organisation = $this->getAdminOrganisation();

        $search = $request->query('q');
        $assets = $this->assets
            ->images($organisation, is_string($search) ? $search : null)
            ->limit(CmsAssets::LIST_LIMIT)
            ->get();

        return new JsonResponse([
            'data' => $assets->map(function ($asset) {
                return $this->assets->present($asset);
            })->values(),
        ]);
    }

    /**
     * POST admin/cms/upload (multipart, field `file`): { id, location, ... }
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request)
    {
        $organisation = $this->getAdminOrganisation();

        // Always JSON (TinyMCE does not ask for it), also for errors.
        $validator = Validator::make($request->all(), CmsAssets::UPLOAD_RULES, [], [ 'file' => 'bestand' ]);
        if ($validator->fails()) {
            return new JsonResponse([
                'message' => $validator->errors()->first(),
                'error' => [ 'message' => $validator->errors()->first() ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $asset = $this->assets->store($request->file('file'), $organisation, \Auth::user());

        return new JsonResponse($this->assets->present($asset));
    }

    /**
     * @return Organisation
     */
    protected function getAdminOrganisation()
    {
        $user = \Auth::user();
        $organisation = $user->getActiveOrganisation();
        if (!$organisation || !$organisation->isAdmin($user)) {
            abort(404);
        }

        return $organisation;
    }
}
