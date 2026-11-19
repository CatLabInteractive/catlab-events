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

use App\Cms\Blog;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\PageTranslation;
use App\Models\Series;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * robots.txt and llms.txt, per organisation (domain) and environment.
 *
 * Class SeoController
 * @package App\Http\Controllers
 */
class SeoController extends Controller
{
    const LLMS_CACHE_TTL = 3600;
    const LLMS_MAX_EVENTS = 25;

    /**
     * Everything is open to every crawler (AI bots included) on an indexable
     * environment; nothing is on staging and other non-production setups.
     * @return \Illuminate\Http\Response
     */
    public function robots()
    {
        if (!config('seo.indexable')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\nDisallow: /admin\nDisallow: /orders/\n\n"
                . 'Sitemap: ' . url('sitemap.xml') . "\n";
        }

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * llms.txt (llmstxt.org): a short Markdown summary of who we are and the
     * pages worth reading, for AI assistants that look for one.
     * @param Blog $blog
     * @return \Illuminate\Http\Response
     */
    public function llms(Blog $blog)
    {
        $organisation = organisation();
        if (!$organisation) {
            abort(404);
        }

        $body = Cache::remember(
            'seo.llms:' . $organisation->id . ':' . md5(request()->root()),
            self::LLMS_CACHE_TTL,
            function () use ($organisation, $blog) {
                return $this->buildLlms($organisation, $blog);
            }
        );

        return response($body, 200)->header('Content-Type', 'text/markdown; charset=UTF-8');
    }

    /**
     * @param Organisation $organisation
     * @param Blog $blog
     * @return string
     */
    protected function buildLlms(Organisation $organisation, Blog $blog): string
    {
        $locale = config('cms.default_locale');
        $lines = [];

        $lines[] = '# ' . $organisation->name;
        $lines[] = '';
        $lines[] = '> ' . $this->oneLine($organisation->getDescription());
        $lines[] = '';

        $facts = [];
        $facts[] = 'Website: ' . url('/');
        if ($organisation->support_email) {
            $facts[] = 'Contact: ' . $organisation->support_email;
        }
        if ($organisation->getPhoneNumber()) {
            $facts[] = 'Telefoon: ' . $organisation->getPhoneNumber();
        }
        foreach ($organisation->getSocialLinks() as $label => $link) {
            $facts[] = $label . ': ' . $link;
        }
        foreach ($facts as $fact) {
            $lines[] = '- ' . $fact;
        }
        $lines[] = '';

        // Upcoming quizzes, with date, place and tickets
        $events = $organisation->events()
            ->select('events.*')
            ->upcoming()
            ->published()
            ->orderByStartDate()
            ->withListRelations()
            ->limit(self::LLMS_MAX_EVENTS)
            ->get();

        $lines[] = '## Upcoming events';
        $lines[] = '';
        if ($events->count() === 0) {
            $lines[] = '- No events announced right now: see ' . action('EventController@calendar');
        }
        foreach ($events as $event) {
            $lines[] = '- ' . $this->link($event->name, $event->getUrl()) . ': ' . $this->describeEvent($event);
        }
        $lines[] = '- ' . $this->link('Calendar', action('EventController@calendar')) . ': every upcoming event';
        $lines[] = '- ' . $this->link('Archive', action('EventController@archive')) . ': past events and scores';
        $lines[] = '';

        // Series
        $series = Series::where('organisation_id', '=', $organisation->id)->where('active', '=', true)->get();
        if ($series->count() > 0) {
            $lines[] = '## Quiz series';
            $lines[] = '';
            foreach ($series as $item) {
                $teaser = $this->oneLine((string) $item->teaser);
                $lines[] = '- ' . $this->link($item->name, $item->getUrl()) . ($teaser !== '' ? ': ' . Str::limit($teaser, 200) : '');
            }
            $lines[] = '';
        }

        // CMS pages (default locale), menu pages first
        $pages = PageTranslation::forOrganisation($organisation)
            ->published()
            ->where('locale', '=', $locale)
            ->whereHas('page', function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('link')->orWhere('link', '=', '');
                });
            })
            ->with('page')
            ->get()
            ->sortBy(function (PageTranslation $translation) {
                return [ $translation->page->show_in_menu ? 0 : 1, $translation->title ];
            });

        if ($pages->count() > 0) {
            $lines[] = '## About';
            $lines[] = '';
            foreach ($pages as $translation) {
                $isHome = $organisation->home_page_id && (int) $organisation->home_page_id === (int) $translation->page_id;
                $url = $isHome ? PageController::localeRootUrl($translation->locale) : $translation->getUrl();
                $summary = $this->oneLine((string) $translation->meta_description);
                $lines[] = '- ' . $this->link($translation->title, $url) . ($summary !== '' ? ': ' . $summary : '');
            }
            $lines[] = '';
        }

        // Blog
        $posts = $blog->visible($organisation, $locale)->limit(10)->get();
        if ($posts->count() > 0) {
            $lines[] = '## Blog';
            $lines[] = '';
            foreach ($posts as $post) {
                $lines[] = '- ' . $this->link($post->title, $post->getUrl());
            }
            $lines[] = '- ' . $this->link('All posts', Blog::indexUrl($locale));
            $lines[] = '';
        }

        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- ' . $this->link('Sitemap', url('sitemap.xml'));

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Event $event
     * @return string
     */
    protected function describeEvent(Event $event): string
    {
        $parts = [];

        if ($event->startDate) {
            $parts[] = $event->startDate->format('Y-m-d H:i');
        }

        if ($event->venue) {
            $parts[] = trim($event->venue->name . ', ' . $event->venue->city, ', ');
        }

        if ($event->series) {
            $parts[] = $event->series->name;
        }

        if (!$event->willSell() && !$event->canRegister()) {
            $parts[] = 'tickets not on sale';
        } elseif ($event->isSoldOut()) {
            $parts[] = 'sold out';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function oneLine(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param string $label
     * @param string $url
     * @return string
     */
    protected function link(string $label, string $url): string
    {
        return '[' . str_replace([ '[', ']' ], [ '(', ')' ], $this->oneLine($label)) . '](' . $url . ')';
    }
}
