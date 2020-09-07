<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Event;
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
            ->orderBy('startDate', 'asc')
            ->get()
            ->filter(function(Event $event) {
                return $event->scores->count() > 0;
            });

        $statistics = [];
        foreach ($events as $event) {
            /** @var Event $event */
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
            'events' => $events,
            'groups' => $teams,
            'statistics' => $statistics,
            'canonicalUrl' => action('CompetitionController@show', $competition->id)
        ]);
    }

    /**
     * @param Event $event
     * @return array
     */
    protected function getScoreParameters(Event $event)
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
    protected function getDifficulty(Event $event)
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
