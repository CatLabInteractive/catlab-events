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
use App\Models\EventDate;
use App\Models\Group;
use App\Models\Organisation;
use App\Models\Score;

/**
 * Class CompetitionController
 * @package App\Http\Controllers
 */
class CompetitionController
{
    const POINT_PER_QUIZ = 1000;
    const MAX_POINTS_PER_QUIZ = 71000;

    /**
     *
     */
    public function index()
    {
        $organisation = Organisation::getRepresentedOrganisation();
        $competitions = $organisation->competitions()->get();

        return view('competitions', [
            'competitions' => $competitions,
            'canonicalUrl' => action('CompetitionController@index')
        ]);
    }

    /**
     * @param $competitionId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($competitionId)
    {
        /** @var Competition $competition */
        $competition = Competition::findOrFail($competitionId);

        $teams = [];

        // Only show events with scores.
        $events = $competition
            ->events()
            ->published()
            ->orderByStartDate()
            ->get()
            ->filter(function(Event $event) {
                $eventDates = $event->eventDates;
                foreach ($eventDates as $eventDate) {
                    if ($eventDate->scores->count() > 0) {
                        return true;
                    }
                }
                return false;
            });

        $statistics = [];

        $eventDates = [];
        foreach ($events as $rootEvent) {
            foreach ($rootEvent->eventDates as $event) {

                /** @var EventDate $event */
                $eventDates[] = $event;

                $scores = $event->scores;

                $averages = $this->getScoreParameters($event);
                $statistics[$event->id] = $averages;

                foreach ($scores as $score) {
                    /** @var Score $score */

                    if ($score->group) {
                        $group = $score->group;
                        $groupKey = $group->id;
                    } else {
                        // Create temporary group
                        $group = new Group();
                        $groupKey = $score->name;
                        $group->name = $score->name;
                    }

                    if (!isset($teams[$groupKey])) {
                        $teams[$groupKey] = [
                            'group' => $group,
                            'events' => [],
                            'totalScore' => 0,
                            'totalWeighted' => 0
                        ];
                    }

                    $weightedScore = ($score->score / $averages['max']) * $averages['difficulty'];
                    $weightedScore *= self::POINT_PER_QUIZ;
                    $weightedScore = round($weightedScore);

                    $teams[$groupKey]['totalScore'] += $score->score;
                    $teams[$groupKey]['totalWeighted'] += $weightedScore;

                    $teams[$groupKey]['events'][$event->id] = [
                        'score' => $score->score,
                        'position' => $score->position,
                        'weightedScore' => $weightedScore
                    ];
                }
            }
        }

        foreach ($teams as $k => $v) {
            $teams[$k]['finalScore'] = $this->getFinalScore($teams[$k]['events']);
            $teams[$k]['valid'] = count($v['events']) >= 2;
        }

        // sort the teams
        usort($teams, function($a, $b) {
            if (!$a['valid'] && $b['valid']) {
                return 1;
            }

            if (!$b['valid'] && $a['valid']) {
                return -1;
            }

            return $b['finalScore'] - $a['finalScore'];
        });

        $i = 0;
        foreach ($teams as $k => $v) {
            $teams[$k]['position'] = ++ $i;
        }

        return view('competition', [
            'upcoming' => $competition->events()->upcoming()->get(),
            'competition' => $competition,
            'eventDates' => $eventDates,
            'groups' => $teams,
            'statistics' => $statistics,
            'canonicalUrl' => action('CompetitionController@show', $competition->id)
        ]);
    }

    /**
     * @param Event $event
     * @return array
     */
    protected function getScoreParameters(EventDate $event)
    {
        $scores = $event->scores->pluck('score');

        // only use values > 0;
        $scores = $scores->filter(function($value) {
            return $value > 0;
        });

        if ($event->max_points) {
            $limit = $event->max_points;
        } else {
            $limit = self::MAX_POINTS_PER_QUIZ;
        }

        return [
            'limit' => $limit,
            'mode' => $scores->average(),
            'max' => $scores->max(),
            'average' => round($scores->average()),
            'difficulty' => $this->getDifficulty($event)
        ];
    }

    /**
     * Calculate quiz difficulty.
     * @param Event $event
     * @return float
     */
    protected function getDifficulty(EventDate $event)
    {
        if ($event->max_points) {
            $limit = $event->max_points;
        } else {
            $limit = self::MAX_POINTS_PER_QUIZ;
        }

        $scores = $event->scores->pluck('score');

        // only use values > 0;
        $scores = $scores->filter(function($value) {
            return $value > 0;
        });

        $hardDifficulty = ($limit / $scores->median()) / 2;
        $softenDifficulty = $hardDifficulty;

        $softFactor = 5;
        $softenDifficulty = (1 - (1 / $softFactor)) + ($hardDifficulty / $softFactor);

        return round($softenDifficulty * 1000) / 1000;
    }

    /**
     * @param $events
     * @return int
     */
    protected function getFinalScore($events)
    {
        // sort on score
        usort($events, function($a, $b) {
            return $b['weightedScore'] - $a['weightedScore'];
        });

        $sum = 0;
        $todo = 2;

        while ($todo > 0 && $next = array_shift($events)) {
            $sum += $next['weightedScore'];
            $todo --;
        }

        return $sum;
    }
}
