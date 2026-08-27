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
use App\Models\Series;
use App\Models\Venue;
use Carbon\Carbon;

/**
 * Class SitemapController
 *
 * Renders sitemap.xml from a Blade view. The roumen/sitemap package that
 * used to do this was dropped in the Laravel 9 upgrade while the route
 * stayed, so /sitemap.xml had been a 500 ever since (Errbit, 2026-08-27).
 *
 * @package App\Http\Controllers
 */
class SitemapController
{
    const CACHE_KEY = 'laravel.sitemap';
    const CACHE_TTL = 3600;

    /**
     * @return \Illuminate\Http\Response
     */
    public function sitemap()
    {
        if (\Request::get('nocache')) {
            \Cache::forget(self::CACHE_KEY);
        }

        $urls = \Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->buildUrls();
        });

        return response()
            ->view('sitemap', [ 'urls' => $urls ])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * @return array[]
     */
    protected function buildUrls()
    {
        $urls = [];

        // Events
        foreach (Event::published()->orderByStartDateDesc()->get() as $event) {
            $urls[] = $this->url($event->getUrl(), $event->updated_at, $this->eventPriority($event), 'weekly');
        }

        // Series
        foreach (Series::all() as $series) {
            $urls[] = $this->url($series->getUrl(), $series->updated_at, $series->active ? 1 : 0.2, 'weekly');
        }

        // Venues
        foreach (Venue::all() as $venue) {
            $urls[] = $this->url($venue->getLocalUrl(), $venue->updated_at, 0.5, 'weekly');
        }

        // Archive & calendar
        $lastEventUpdate = Event::max('updated_at');
        $lastEventUpdate = $lastEventUpdate ? Carbon::parse($lastEventUpdate) : null;

        $urls[] = $this->url(action('EventController@archive'), $lastEventUpdate, 1, 'daily');
        $urls[] = $this->url(action('EventController@calendar'), $lastEventUpdate, 1, 'daily');

        // Competitions
        foreach (Competition::all() as $competition) {
            $urls[] = $this->url(action('CompetitionController@show', [ $competition->id ]), $competition->updated_at, 0.3, 'weekly');
        }

        return $urls;
    }

    /**
     * Upcoming events rank highest; past events decay towards 0.1 over five years.
     *
     * @param Event $event
     * @return float
     */
    protected function eventPriority(Event $event)
    {
        if (!$event->startDate) {
            return 1;
        }

        $timeDiff = time() - $event->startDate->getTimestamp();
        if ($timeDiff < 0) {
            return 1;
        }

        $years = 1 + ($timeDiff / (365 * 24 * 60 * 60));
        $priority = 1 - ($years / 5);
        if ($priority < 0.1) {
            $priority = 0.1;
        }

        return round($priority * 100) / 100;
    }

    /**
     * @param string $loc
     * @param \DateTimeInterface|null $lastmod
     * @param float $priority
     * @param string $changefreq
     * @return array
     */
    protected function url($loc, $lastmod, $priority, $changefreq)
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? $lastmod->format('c') : null,
            'priority' => $priority,
            'changefreq' => $changefreq
        ];
    }
}
