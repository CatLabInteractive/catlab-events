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

namespace App\Http\Middleware;

use App\Models\OrganisationDomain;
use Closure;
use Illuminate\Http\Request;

/**
 * Sends visitors of a secondary organisation domain (for instance
 * tickets.quizfabriek.be) to the same path on the organisation's canonical
 * domain (organisation_domains.is_canonical), with a 301 so search engines
 * move their ranking along. Behind config('seo.canonical_redirect').
 *
 * Only GET and HEAD redirect: a POST (payment webhook, form) would lose its
 * body on the way.
 *
 * Class CanonicalDomain
 * @package App\Http\Middleware
 */
class CanonicalDomain
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!config('seo.canonical_redirect')) {
            return $next($request);
        }

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($request->is(...config('seo.canonical_redirect_except', []))) {
            return $next($request);
        }

        $canonicalHost = $this->canonicalHostFor($request->getHost());
        if ($canonicalHost === null) {
            return $next($request);
        }

        $target = $request->getScheme() . '://' . $canonicalHost . $request->getRequestUri();

        return redirect()->to($target, 301);
    }

    /**
     * The canonical domain of the organisation that owns $host, or null when
     * $host is unknown, is itself canonical, or the organisation has none.
     * @param string $host
     * @return string|null
     */
    protected function canonicalHostFor(string $host): ?string
    {
        $domain = OrganisationDomain::where('domain', '=', $host)->first();
        if (!$domain || $domain->is_canonical) {
            return null;
        }

        $canonical = OrganisationDomain::where('organisation_id', '=', $domain->organisation_id)
            ->where('is_canonical', '=', true)
            ->orderBy('id')
            ->first();

        if (!$canonical || strcasecmp($canonical->domain, $host) === 0) {
            return null;
        }

        return $canonical->domain;
    }
}
