<?php

namespace App\Http\Controllers;

/**
 * Class HomeController
 * @package App\Http\Controllers
 */
class HomeController extends Controller
{
    public function home()
    {
        return redirect('/events');
    }

    public function admin()
    {
        return redirect('/admin/events');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function press()
    {
        return redirect('https://drive.google.com/open?id=1MN4FMEE3x24CpLt06CIwRtiDakc4aK4l');
    }
}
