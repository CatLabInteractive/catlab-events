<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Session;
use Spatie\Referer\Referer;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @return array
     */
    protected function getEuklesOriginWebsite()
    {
        $referer = app(Referer::class)->get();

        return [
            'type' => 'session',
            'uid' => Session::getId(),
            'url' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'referrer' => $referer
        ];
    }

    /**
     * Get the organisation that is represented by this website.
     */
    public function getOrganisation()
    {
        return Organisation::getRepresentedOrganisation();
    }
}
