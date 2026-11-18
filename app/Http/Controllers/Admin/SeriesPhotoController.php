<?php

namespace App\Http\Controllers\Admin;

use App\Models\Series;
use CatLab\Charon\Enums\Action;
use Illuminate\Http\Request;

/**
 * Admin for the hero slideshow photos of a series (Series > Photos).
 */
class SeriesPhotoController extends BaseAdminController
{
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\SeriesPhotoController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\SeriesPhotoController::RESOURCE_ID;
    }

    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'series' => $request->route('series')
        ];
    }

    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    'series' => $request->route('series')
                ];
        }

        return [];
    }

    protected function getAuthorizeParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    Series::find($request->route('series'))
                ];
        }

        return [];
    }
}
