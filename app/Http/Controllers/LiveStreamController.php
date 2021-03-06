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

use App\Exceptions\LivestreamNotFoundException;
use App\Models\LiveStream;
use App\Models\User;
use App\Tools\RocketChatClient;
use App\UitDB\Exceptions\UitPASException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Class LiveStreamController
 * @package App\Http\Controllers
 */
class LiveStreamController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $embed = $request->query('embed') == 1;
        return view('livestream/notfound', [
            'organisation' => $this->getOrganisation(),
            'embed' => $embed
        ]);
    }

    /**
     * @param Request $request
     * @param $domain
     * @param null $identifier
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     * @throws LivestreamNotFoundException
     */
    public function view(Request $request, $domain, $identifier = null)
    {
        $stream = $this->getStream($domain, $identifier);
        $embed = $request->query('embed') == 1;

        $user = \Auth::user();

        // Do we have rocket chat?
        $rocketChatUrl = false;
        $rocketChatToken = null;
        $hasChat = false;

        $rocketChatAuthUrl = null;

        // Are used required to login in order to chat?
        $mustLoginToChat = $stream->chat_require_login;

        if (
            $stream->rocketchat_channel &&
            $stream->organisation->rocketchat_url
        ) {
            $hasChat = true;
            $mustLoginToChat = true;

            // only show chat when user has logged in
            if ($user) {
                $rocketChatUrl = $stream->organisation->rocketchat_url . '/channel/' . $stream->rocketchat_channel;

                $rocketChatAuthUrl = action('LiveStreamController@getRocketAuthToken', [
                    'identifier' => $stream->token
                ]);
            }
        } elseif ($stream->deadsimple_chat_url) {
            $hasChat = true;
        }

        if ($stream->redirect_uri) {
            return redirect($stream->redirect_uri);
        }

        // Are we live?
        $viewToLoad = 'livestream.waiting';

        $secretPreview = $request->query('secretPreviewThijs');
        if ($stream->streaming || $secretPreview) {
            if ($stream->redirect_uri) {
                return redirect($stream->redirect_uri);
            }
            $viewToLoad = 'livestream.view';
        }

        $username = $request->query('n');

        $deadSimpleChatUrl = $stream->deadsimple_chat_url;
        if ($deadSimpleChatUrl) {
            if ($username) {
                $deadSimpleChatUrl .= '?username=' . urlencode($username);
                $mustLoginToChat = false;
            } elseif ($user) {
                $deadSimpleChatUrl .= '?username=' . urlencode($this->getChatNickname($user, $stream));
            } elseif ($mustLoginToChat) {
                $deadSimpleChatUrl = null;
            }
        }

        return view($viewToLoad, [
            'livestream' => $stream,
            'organisation' => $stream->organisation,
            'poll' => action('LiveStreamController@poll', [ $stream->token ]),
            'embed' => $embed,
            'rocketChatUrl' => $rocketChatUrl,
            'hasChat' => $hasChat,
            'user' => $user,
            'loginUrl' => action('LiveStreamController@viewLogin', [
                'identifier' => $stream->token
            ]),
            'rocketChatAuthUrl' => $rocketChatAuthUrl,
            'deadSimpleChat' => $deadSimpleChatUrl,
            'username' => $username,
            'mustLoginToChat' => $mustLoginToChat,
            'code' => $request->input('code')
        ]);
    }

    /**
     * @param Request $request
     * @param $domain
     * @param null $identifier
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     * @throws LivestreamNotFoundException
     */
    public function viewLogin(Request $request, $domain, $identifier = null)
    {
        $stream = $this->getStream($domain, $identifier);
        //return redirect(route('livestream_view', [ $stream->token ]));
        $path = '/' . $request->path();

        // Get rid of the 'login' postfix

        // http://live.events.catlab.local.com/livestreams/5kf92tr6ZzLTGO2f/login
        $path = Str::substr($path, 0, -6);
        return redirect($path);
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

    /**
     * @param Request $request
     * @param $domain
     * @param null $identifier
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws LivestreamNotFoundException
     */
    public function uitPASCheckin(Request $request, $domain, $identifier = null)
    {
        $stream = $this->getStream($domain, $identifier);

        $event = $stream->event;
        if (!$event) {
            throw new LivestreamNotFoundException();
        }

        $uitpas = \UitDb::getUitPasService()->canCheckIn($event);
        if (!$uitpas) {
            throw new LivestreamNotFoundException();
        }

        return view(
            'livestream.uitpasCheckin',
            [
                'organisation' => $stream->organisation,
                'livestream' => $stream,
                'embed' => false,
                'success' => null,
                'error' => null
            ]
        );
    }

    public function processUitPASCheckin(Request $request, $domain, $identifier = null)
    {
        $stream = $this->getStream($domain, $identifier);

        $event = $stream->event;
        if (!$event) {
            throw new LivestreamNotFoundException();
        }

        $success = null;
        $error = null;
        try {
            $result = \UitDb::getUitPasService()->uitPASCheckin($stream->event, $request->input('uitpasNumber'));
            if ($result) {
                $success = 'UiTPAS code aanvaard.';
            }
        } catch (UitPASException $e) {
            $error = $e->getMessage();
        }

        return view(
            'livestream.uitpasCheckin',
            [
                'organisation' => $stream->organisation,
                'livestream' => $stream,
                'embed' => false,
                'success' => $success,
                'error' => $error
            ]
        );
    }

    /**
     * @param Request $request
     * @param $domain
     * @param null $identifier
     * @return \Illuminate\Http\JsonResponse
     * @throws LivestreamNotFoundException
     */
    public function getRocketAuthToken(Request $request, $domain, $identifier = null)
    {
        $stream = $this->getStream($domain, $identifier);

        $user = \Auth::user();

        return \Response::json([
            'authToken' => $this->getRocketChatLoginToken($request, $stream)
        ]);
    }

    /**
     * @param $domain
     * @param null $identifier
     * @return LiveStream
     * @throws LivestreamNotFoundException
     */
    protected function getStream($domain, $identifier = null)
    {
        if (empty($identifier)) {
            $identifier = $domain;
            $domain = null;
        }

        $stream = LiveStream::where('token', '=', $identifier)->first();
        if (!$stream) {
            throw new LivestreamNotFoundException();
        }

        // set app locale to language.
        $language = \Request::query('lang');
        if ($language && file_exists(resource_path("lang/$language"))) {
            \Session::put('language', $language);
            \App::setLocale($language);
        } elseif (($language = \Session::get('language')) && file_exists(resource_path("lang/$language"))) {
            \App::setLocale($language);
        } elseif ($stream->language) {
            \App::setLocale($stream->language);
        }

        return $stream;
    }

    /**
     * @param Request $request
     * @param LiveStream $stream
     * @return string|null
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function getRocketChatLoginToken(Request $request, LiveStream $stream)
    {
        $user = $request->user();
        if(!$user) {
            return null;
        }

        $sessionName = 'rocket_token_' . $user->id;
        if ($request->session()->has($sessionName)) {
            return $request->session()->get($sessionName);
        }

        $rocketUsername = Str::slug($user->name);
        $rocketPassword = md5(implode(',', [ $rocketUsername, $user->email, $user->created_at, config('app.key') ]));

        $nickname = $this->getChatNickname($user, $stream);

        $client = new RocketChatClient(
            $stream->organisation->rocketchat_url,
            $stream->organisation->rocketchat_admin_username,
            $stream->organisation->rocketchat_admin_password
        );
        $accessToken = $client->getAuthToken(
            $rocketUsername,
            $rocketPassword,
            'qw' . $user->id . '@events.catlab.eu',
            $nickname,
            $stream->rocketchat_channel
        );

        return $accessToken;
    }

    /**
     * @param User $user
     * @param LiveStream $stream
     * @return string
     */
    protected function getChatNickname(User $user, LiveStream $stream)
    {
        if (!$stream->event) {
            return $user->name;
        }

        $groups = $user->groups;
        foreach ($groups as $group) {
            if ($stream->event->isRegistered($group)) {
                return $group->name;
            }
        }

        return $user->name;
    }
}
