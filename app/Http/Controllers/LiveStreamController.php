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

namespace App\Http\Controllers;

use App\Models\LiveStream;
use Illuminate\Http\Request;

/**
 * Class LiveStreamController
 * @package App\Http\Controllers
 */
class LiveStreamController extends Controller
{
    /**
     *
     */
    public function index()
    {
        return view('livestream/notfound', [
            'organisation' => $this->getOrganisation()
        ]);
    }

    /**
     * @param $domain
     * @param null $identifier
     * @return \Illuminate\View\View
     */
    public function view(Request $request, $domain, $identifier = null)
    {
        if (empty($identifier)) {
            $identifier = $domain;
            $domain = null;
        }

        $stream = LiveStream::where('token', '=', $identifier)->first();
        if (!$stream) {
            return view(
                'livestream.notfound',
                [
                    'organisation' => $this->getOrganisation()
                ]
            );
        }

        $secretPreview = $request->query('secretPreviewThijs');
        if (!$stream->streaming && !$secretPreview) {
            return view(
                'livestream.waiting',
                [
                    'livestream' => $stream,
                    'organisation' => $stream->organisation,
                    'poll' => action('LiveStreamController@poll', [ $stream->token ])
                ]
            );
        }

        if ($stream->redirect_uri) {
            return redirect($stream->redirect_uri);
        }

        return view('livestream.view', [
            'livestream' => $stream,
            'organisation' => $stream->organisation,
        ]);
    }

    /**
     * @param $identifier
     * @return \Illuminate\Http\JsonResponse
     */
    public function poll($identifier)
    {
        $stream = LiveStream::where('token', '=', $identifier)->first();
        if (!$stream) {
            abort(404);
        }

        if ($stream->streaming) {
            $viewUrl = route('livestream_view', [ $stream->token ]);
            return \Response::json([ 'redirect' => $viewUrl]);
        } else {
            return \Response::json([
                'wait' => 30 * 1000
            ]);
        }
    }

}
