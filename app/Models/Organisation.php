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

namespace App\Models;

use App\Exceptions\NoOrganisationsFoundException;
use App\Tools\StringHelper;
use Carbon\Carbon;
use CatLab\CentralStorage\Client\Models\Asset;
use CatLab\Charon\Laravel\Database\Model;
use DB;
use Illuminate\Support\Str;

/**
 * Class Organisation
 * @package App\Models
 */
class Organisation extends Model
{
    const ROLE_ADMIN = 10;

    protected $fillable = ['name'];

    /**
     * @var int[]
     */
    private $userRoleCache = [];

    /**
     * @return Organisation
     */
    public static function getRepresentedOrganisation()
    {
        static $representedOrganisation;

        if (!isset($representedOrganisation)) {
            $representedOrganisation = self::getFromDomainOrFirst($_SERVER['HTTP_HOST']);
        }

        return $representedOrganisation;
    }

    /**
     * @param string $domain
     * @return Organisation
     */
    public static function getFromDomainOrFirst($domainName)
    {
        // Strip subdomain in some specific cases
        $parts = explode('.', $domainName);
        switch (Str::lower($parts[0])) {
            case 'live':
                array_shift($parts);
                $domainName = implode('.', $parts);
                break;
        }

        $domain = OrganisationDomain::where('domain', '=', $domainName)->first();
        if ($domain) {
            return $domain->organisation;
        }

        return Organisation::first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organisation')->withTimestamps();
    }

    /**
     * @return mixed
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return mixed
     */
    public function people()
    {
        return $this->hasMany(Person::class);
    }

    /**
     * @return mixed
     */
    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function domains()
    {
        return $this->hasMany(OrganisationDomain::class);
    }

    /**
     * @return mixed
     */
    public function series()
    {
        return $this->hasMany(Series::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function livestreams()
    {
        return $this->hasMany(LiveStream::class);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isAdmin(User $user)
    {
        return $this->getUserRole($user) === 10;
    }

    /**
     * Should we collect (Belgian) VAT on the transaction fees?
     */
    public function getFeeVatFactor()
    {
        if ($this->fee_vat_factor !== null) {
            return $this->fee_vat_factor;
        }
        return 0.21;
    }

    /**
     * @return float
     */
    public function getTransactionFeeFixed()
    {
        if ($this->fee_fixed !== null) {
            return $this->fee_fixed;
        }
        return 0.5;
    }

    /**
     * @return float
     */
    public function getTransactionFeeFactor()
    {
        if ($this->fee_factor !== null) {
            return $this->fee_factor;
        }
        return 0.03;
    }

    /**
     * @return int
     */
    public function getTransactionFeeMinimum()
    {
        if ($this->fee_minimum !== null) {
            return $this->fee_minimum;
        }
        return 1;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function logo()
    {
        return $this->belongsTo(Asset::class, 'logo_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function favicon()
    {
        return $this->belongsTo(Asset::class, 'favicon_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class, Event::class);
    }

    /**
     * @return int
     */
    public function countGroups()
    {
        return $this->orders()->accepted()->distinct()->count();
    }

    /**
     * @return int
     */
    public function countPlayers()
    {
        return $this->orders()->accepted()->count() * 4;
    }

    /**
     * @return Series|null
     */
    public function getRandomSeries()
    {
        return $this->series()->orderBy(DB::raw('RAND()'))->first();
    }

    /**
     * @return mixed
     */
    public function getLegalName()
    {
        if ($this->legal_name) {
            return $this->legal_name;
        } else {
            return $this->name;
        }
    }

    /**
     * @return bool
     */
    public function showSponsors()
    {
        return $this->id == 1;
    }

    /**
     * @return array
     */
    public function getJsonLd()
    {
        $out = [
            "@context" => "http://schema.org",
            "@type" => "Organization",
            "name" => $this->name ,
            "url" => request()->root(),
            "sameAs" => [

            ]
        ];

        $sameAs = [
            'website_url',
            'facebook_url',
            'youtube_url',
            'instagram_url',
            'googleplus_url',
            'twitter_url'
        ];

        foreach ($sameAs as $v) {
            if ($this->$v) {
                $out['sameAs'][] = $this->$v;
            }
        }

        return $out;
    }

    /**
     * @return string[]
     */
    public function getOrganisationJsonLd()
    {
        $out = [
            "@context" => "http://schema.org",
            "@type" => "Organization",
            "name" => $this->name,
            "url" => request()->root()
        ];

        if ($this->logo) {
            $out['logo'] = $this->logo->getUrl();
        }

        return $out;
    }

    /**
     * @return array
     */
    public function getSocialLinks()
    {
        $out = [];

        if ($this->facebook_url) {
            $out['Facebook'] = $this->facebook_url;
        }

        if ($this->twitter_url) {
            $out['Twitter'] = $this->twitter_url;
        }

        if ($this->youtube_url) {
            $out['YouTube'] = $this->youtube_url;
        }

        if ($this->instagram_url) {
            $out['Instagram'] = $this->instagram_url;
        }

        if ($this->linkedin_url) {
            $out['LinkedIn'] = $this->linkedin_url;
        }

        if ($this->googleplus_url) {
            $out['Google+'] = $this->googleplus_url;
        }

        return $out;
    }

    /**
     * @return string
     */
    public function getSocialLinksText()
    {
        $links = $this->getSocialLinks();

        $text = '';

        $index = 0;
        foreach ($links as $k => $v) {
            $text .= '<a href="' . $v . '" target="_blank">' . $k . '</a>';

            if ($index < count($links) - 2) {
                $text .= ', ';
            } else {
                $text .= ' ' . __('organisation.and') . ' ';
            }

            $index ++;
        }

        $text = Str::substr($text, 0, -4);

        return __('organisation.followUs', [ 'links' => $text ]);
    }

    /**
     * @return string
     */
    public function getTeamName()
    {
        return $this->name . ' team';
    }

    /**
     * @return string|null
     */
    public function getContactOptionsText()
    {
        $options = [];
        if ($this->support_email) {
            $options[] = '<a href="mailto:' . $this->support_email . '" target="_blank">' . $this->support_email . '</a>';
        }

        if ($this->messenger_url) {
            $options[] = '<a href="' . $this->messenger_url . '" target="_blank">facebook messenger</a>';
        }

        if (count($options) > 0) {
            return 'Neem contact met ons op via ' . implode(' of ', $options) . '.';
        }
        return null;
    }

    public function getFirstEventDate()
    {
        $firstEventDate = $this->events()
            ->leftJoin('event_dates', 'events.id', '=', 'event_dates.event_id')
            ->min('event_dates.startDate');

        if (!$firstEventDate) {
            return null;
        }

        // @todo fix proper.
        return new Carbon($firstEventDate);
    }

    /**
     * @param User $user
     * @return int
     */
    protected function getUserRole(User $user)
    {
        if (isset($this->userRoleCache[$user->id])) {
            return $this->userRoleCache[$user->id];
        }

        $users = $this->users()->withPivot('role')->get();
        foreach ($users as $v) {
            if ($v->equals($user)) {
                $this->userRoleCache[$user->id] = intval($v->pivot->role);
                return $this->userRoleCache[$user->id];
            }
        }

        $this->userRoleCache[$user->id] = null;
        return $this->userRoleCache[$user->id];
    }
}
