<?php

namespace App\Tools;

/**
 * Class CountdownHelper
 * @package App\Tools
 */
class CountdownHelper
{
    /**
     * @param \DateTime $dateTime
     * @return array
     */
    public static function getCountdown(\DateTime $dateTime)
    {
        $time = $dateTime->getTimestamp() - time();
        if ($time < 0) {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0
            ];
        }

        $out = [];
        $out['days'] = floor($time / (24 * 60 * 60));
        $time -= $out['days'] * 24 * 60 * 60;

        $out['hours'] = floor($time / (60 * 60));
        $time -= $out['hours'] * 60 * 60;

        $out['minutes'] = floor($time / (60));
        $time -= $out['minutes'] * 60;

        $out['seconds'] = $time;

        if ($out['days'] < 10) {
            $out['days'] = '0' . $out['days'];
        }

        if ($out['hours'] < 10) {
            $out['hours'] = '0' . $out['hours'];
        }

        if ($out['minutes'] < 10) {
            $out['minutes'] = '0' . $out['minutes'];
        }

        if ($out['seconds'] < 10) {
            $out['seconds'] = '0' . $out['seconds'];
        }

        return $out;
    }
}