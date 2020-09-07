<?php

namespace App\Http\Controllers;

use App;
use URL;

/**
 * Class SitemapController
 * @package App\Http\Controllers
 */
class SitemapController
{

    public function sitemap()
    {
        // create new sitemap object
        $sitemap = App::make('sitemap');

        // set cache key (string), duration in minutes (Carbon|Datetime|int), turn on/off (boolean)
        // by default cache is disabled
        $sitemap->setCache('laravel.sitemap', 60);

        // check if there is cached sitemap and build new only if is not
        if (!$sitemap->isCached() || !\Request::get('nocache')) {


            // Events
            foreach (App\Models\Event::orderBy('startDate', 'desc')->get() as $event) {

                $timeDiff = (time() - $event->startDate->getTimestamp());
                if ($timeDiff < 0) {
                    $priority = 1;
                } else {
                    $years = 1 + ($timeDiff / (365 * 24 * 60 * 60));
                    $priority = 1 - ($years / 5);
                    if ($priority < 0.1) {
                        $priority = 0.1;
                    }
                    $priority = round($priority * 100) / 100;
                }

                $sitemap->add(
                    $event->getUrl(),
                    $event->updated_at->format('c'),
                    $priority,
                    'weekly'
                );
            }

            // Series
            foreach (App\Models\Series::all() as $series) {
                $priority = $series->active ? 1 : 0.2;

                $sitemap->add(
                    $series->getUrl(),
                    $series->updated_at->format('c'),
                    $priority,
                    'weekly'
                );
            }

            // Venues
            foreach (App\Models\Venue::all() as $venue) {
                $priority = 0.5;

                $sitemap->add(
                    $venue->getLocalUrl(),
                    $venue->updated_at->format('c'),
                    $priority,
                    'weekly'
                );
            }

            // Archive
            $sitemap->add(
                action('EventController@archive'),
                App\Models\Event::max('updated_at'),
                1,
                'daily'
            );

            // Calendar
            $sitemap->add(
                action('EventController@calendar'),
                App\Models\Event::max('updated_at'),
                1,
                'daily'
            );

            // Competitions
            foreach (App\Models\Competition::all() as $competition) {
                $priority = 0.3;

                $sitemap->add(
                    action('CompetitionController@show', [ $competition->id ]),
                    $competition->updated_at->format('c'),
                    $priority,
                    'weekly'
                );
            }

        }

        // show your sitemap (options: 'xml' (default), 'html', 'txt', 'ror-rss', 'ror-rdf')
        return $sitemap->render('xml');
    }

}