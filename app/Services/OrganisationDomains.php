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

namespace App\Services;

use App\Models\OrganisationDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves and deletes organisation domains with the rules that keep the site
 * reachable:
 *  - a domain is a bare host name (lower case, no scheme, port or path) and
 *    belongs to one organisation only, since requests are matched by host;
 *  - an organisation has at most one canonical domain: marking one clears
 *    the mark on the others;
 *  - the domain the admin is working on can't be deleted or renamed (the
 *    site would fall back to another organisation mid-session).
 *
 * Class OrganisationDomains
 * @package App\Services
 */
class OrganisationDomains
{
    /**
     * "https://Www.Example.be/path" → "www.example.be".
     * @param mixed $domain
     * @return string
     */
    public static function normalise($domain): string
    {
        $domain = strtolower(trim((string) $domain));

        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
        $domain = preg_replace('#[/?\#].*$#', '', $domain);
        $domain = preg_replace('#:\d+$#', '', $domain);

        return rtrim($domain, '.');
    }

    /**
     * @param OrganisationDomain $domain
     * @param string|null $currentHost The host the admin is on.
     * @return OrganisationDomain
     * @throws ValidationException
     */
    public function save(OrganisationDomain $domain, ?string $currentHost = null): OrganisationDomain
    {
        $domain->domain = self::normalise($domain->domain);
        $domain->is_canonical = (bool) $domain->is_canonical;

        $this->validate($domain);

        if ($domain->exists && $domain->isDirty('domain') && $this->isCurrentHost($domain->getOriginal('domain'), $currentHost)) {
            throw ValidationException::withMessages([
                'domain' => 'Je werkt nu op dit domein. Pas het aan vanaf een ander domein van de organisatie.',
            ]);
        }

        return DB::transaction(function () use ($domain) {
            $domain->save();

            if ($domain->is_canonical) {
                OrganisationDomain::where('organisation_id', '=', $domain->organisation_id)
                    ->where('id', '!=', $domain->id)
                    ->where('is_canonical', '=', true)
                    ->update([ 'is_canonical' => false ]);
            }

            return $domain;
        });
    }

    /**
     * @param OrganisationDomain $domain
     * @param string|null $currentHost
     * @throws ValidationException
     */
    public function delete(OrganisationDomain $domain, ?string $currentHost = null): void
    {
        if ($this->isCurrentHost($domain->domain, $currentHost)) {
            throw ValidationException::withMessages([
                'domain' => 'Je werkt nu op dit domein. Verwijder het vanaf een ander domein van de organisatie.',
            ]);
        }

        $domain->delete();
    }

    /**
     * @param OrganisationDomain $domain
     * @throws ValidationException
     */
    protected function validate(OrganisationDomain $domain): void
    {
        $unique = Rule::unique('organisation_domains', 'domain');
        if ($domain->exists) {
            $unique->ignore($domain->id);
        }

        Validator::make([
            'domain' => $domain->domain,
        ], [
            'domain' => [
                'required',
                'string',
                'max:253',
                // Labels of letters, digits and hyphens, with at least one dot.
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/',
                $unique,
            ],
        ], [
            'domain.regex' => 'Vul enkel de domeinnaam in, bijvoorbeeld www.quizfabriek.be.',
            'domain.unique' => 'Dit domein is al in gebruik.',
        ], [
            'domain' => 'domein',
        ])->validate();
    }

    /**
     * @param string|null $domain
     * @param string|null $currentHost
     * @return bool
     */
    protected function isCurrentHost(?string $domain, ?string $currentHost): bool
    {
        return $domain !== null && $currentHost !== null
            && strcasecmp(self::normalise($domain), self::normalise($currentHost)) === 0;
    }
}
