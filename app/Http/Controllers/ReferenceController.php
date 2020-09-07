<?php

namespace App\Http\Controllers;

use App\Models\Series;

/**
 * Class ReferenceController
 * @package App\Http\Controllers
 */
class ReferenceController
{
    /**
     *
     */
    public static function routes()
    {
        $routes = self::getReferences();

        foreach ($routes as $k => $v) {
            \Route::get($k, 'ReferenceController@redirect');
        }
    }

    /**
     * @return array
     */
    public static function getReferences()
    {
        return [
            'palaver' => Series::find(1)->getUrl()
        ];
    }

    /**
     *
     */
    public function redirect()
    {
        $path = \Route::getCurrentRoute()->uri;

        $routes = self::getReferences();
        if (isset($routes[$path])) {
            $url = $routes[$path];
        } else {
            $url = Series::find(1)->getUrl();
        }

        $url = $this->getTaggedUrl($url, $path);
        return redirect($url);
    }

    /**
     * @param $url
     * @param $name
     * @return string
     */
    private function getTaggedUrl($url, $name)
    {
        $url .= '?' . http_build_query
            ([
                'utm_source' => 'offline',
                'utm_medium' => 'podcast',
                'utm_term' => $name
            ]);

        return $url;
    }
}