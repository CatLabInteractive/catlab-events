<?php

namespace App\Http\Controllers;


/**
* Class DocumentController
* @package App\Http\Controllers
*/
class DocumentController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tos()
    {
        return redirect('https://accounts.catlab.eu/docs/nl/events');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function privacy()
    {
        return redirect('https://accounts.catlab.eu/docs/nl/privacy');
    }
}
