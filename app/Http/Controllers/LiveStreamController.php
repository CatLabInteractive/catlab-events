<?php

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
