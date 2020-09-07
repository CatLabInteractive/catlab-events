<?php

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
                'pastEvents' => $series->events()->published()->finished()->limit(10)->orderBy('startDate', 'desc')->get(),
                'canonicalUrl' => action('SeriesController@view', [ $seriesId ])
            ]
        );
    }
}