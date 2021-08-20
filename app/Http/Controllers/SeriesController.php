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

use App\Models\Competition;
use App\Models\Event;
use App\Models\Group;
use App\Models\Score;
use App\Models\Series;
use Illuminate\Http\Request;

/**
 * Class CompetitionController
 * @package App\Http\Controllers
 */
class SeriesController
{
    /**
     *
     */
    public function index()
    {
        $competitions = Competition::all();

        return view('competitions', [
            'competitions' => $competitions
        ]);
    }

    /**
     * @param Request $request
     * @param $seriesId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view(Request $request, $seriesId)
    {
        /** @var Series $series */
        $series = Series::findOrFail($seriesId);

        $nextEvent = null;
        $eventId = $request->get('event');
        if ($eventId) {
            $nextEvent = $series
                ->events()
                ->published()
                ->upcoming()
                ->where('id', '=', $eventId)
                ->first();

            // Also store in session
            if ($nextEvent) {
                $request->session()->put('eventFocusId', $nextEvent->id);
            }
        } elseif ($request->session()->has('eventFocusId')) {
            $nextEvent = $series
                ->events()
                ->published()
                ->upcoming()
                ->where('id', '=', $request->session()->get('eventFocusId'))
                ->first();
        }

        if ($nextEvent === null) {
            $nextEvent = $series->getNextSellingEvent();
        }

        return view(
            'series.view',
            [
                'series' => $series,
                'nextEvent' => $nextEvent,
                'events' => $series->events()->upcoming()->published()->get(),
                'pastEvents' => $series->events()->published()->finished()->limit(10)->orderByStartDateDesc()->get(),
                'canonicalUrl' => action('SeriesController@view', [ $seriesId ])
            ]
        );
    }
}
