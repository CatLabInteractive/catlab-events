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

use App\Cms\HtmlSanitizer;
use App\Exceptions\NoOrganisationsFoundException;
use App\Tools\ColourPalette;
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
     * @var ColourPalette
     */
    private $colourPaletteCache;

    /**
     * Memoised per process by getRepresentedOrganisation().
     * @var Organisation|null
     */
    private static $representedOrganisation;

    /**
     * footer_html is printed unescaped on every page: sanitise it on every
     * write (admin, API, tinker) with the same rules as CMS rich text.
     */
    protected static function booted()
    {
        // A new home page leaves the menu (and the old one may join it).
        static::saved(function (Organisation $organisation) {
            if ($organisation->wasChanged('home_page_id')) {
                app(\App\Cms\Menu::class)->forget((int) $organisation->id);
            }
        });

        static::saving(function (Organisation $organisation) {
            if ($organisation->isDirty('footer_html') && $organisation->footer_html !== null) {
                $clean = app(HtmlSanitizer::class)->sanitize($organisation->footer_html);
                $organisation->footer_html = $clean === '' ? null : $clean;
            }
        });
    }

    /**
     * @return Organisation
     */
    public static function getRepresentedOrganisation()
    {
        if (!isset(self::$representedOrganisation)) {
            self::$representedOrganisation = self::getFromDomainOrFirst($_SERVER['HTTP_HOST'] ?? '');
        }

        return self::$representedOrganisation;
    }

    /**
     * Forget the memoised organisation so the next call resolves it from
     * $_SERVER['HTTP_HOST'] again. Tests only.
     */
    public static function resetRepresentedOrganisation()
    {
        self::$representedOrganisation = null;
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailTemplates()
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function emailLayout()
    {
        return $this->hasOne(EmailLayout::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cmsRedirects()
    {
        return $this->hasMany(CmsRedirect::class);
    }

    /**
     * The CMS page rendered at / (and /en, /fr) instead of the series page.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function homePage()
    {
        return $this->belongsTo(Page::class, 'home_page_id');
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
     * @return mixed
     */
    public function countEvents($includePrivate = true)
    {
        return $this->events()->count() + ($includePrivate ? $this->private_events_count : 0);
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
    /**
     * The series colours used by the site design.
     * @return ColourPalette
     */
    public function getColourPalette()
    {
        if (!isset($this->colourPaletteCache)) {
            $this->colourPaletteCache = new ColourPalette($this->theme_colours);
        }
        return $this->colourPaletteCache;
    }

    /**
     * @return string
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
     * Schema.org Organization: who we are, how to reach us and where else we
     * live online. Search engines and AI assistants use this to tell what the
     * organisation is, so it is the one Organization block on every page and
     * the organizer of every event.
     * @return array
     */
    public function getJsonLd()
    {
        $out = [
            "@context" => "http://schema.org",
            "@type" => "Organization",
            "name" => $this->name,
            "url" => request()->root(),
            "description" => $this->getDescription(),
        ];

        if ($this->legal_name && $this->legal_name !== $this->name) {
            $out['legalName'] = $this->legal_name;
        }

        if ($this->logo) {
            $out['logo'] = $this->logo->getUrl();
        }

        $phone = $this->getPhoneNumber();
        if ($this->support_email || $phone) {
            $contactPoint = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
            ];
            if ($this->support_email) {
                $out['email'] = $this->support_email;
                $contactPoint['email'] = $this->support_email;
            }
            if ($phone) {
                $out['telephone'] = $phone;
                $contactPoint['telephone'] = $phone;
            }
            $out['contactPoint'] = $contactPoint;
        }

        $address = trim(strip_tags((string) $this->address));
        if ($address !== '') {
            $out['address'] = preg_replace('/\s*\n\s*/', ', ', $address);
        }

        $sameAs = array_values($this->getSocialLinks());
        if ($this->website_url && rtrim($this->website_url, '/') !== rtrim(request()->root(), '/')) {
            $sameAs[] = $this->website_url;
        }
        if (count($sameAs) > 0) {
            $out['sameAs'] = array_values(array_unique($sameAs));
        }

        return $out;
    }

    /**
     * The public phone number as entered in the admin, or null.
     * @return string|null
     */
    public function getPhoneNumber(): ?string
    {
        $phone = trim((string) $this->phone);
        return $phone === '' ? null : $phone;
    }

    /**
     * tel: link for the phone number: digits only, keeping a leading + (a
     * leading 00 becomes +), e.g. "+32 (0)9 123 45 67" -> "tel:+3291234567".
     * @return string|null
     */
    public function getPhoneHref(): ?string
    {
        $phone = $this->getPhoneNumber();
        if ($phone === null) {
            return null;
        }

        // The Belgian/Dutch "(0)" trunk prefix is dropped after a country code.
        $phone = preg_replace('/\(0\)/', '', $phone);
        $international = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/\D+/', '', $phone);
        if ($international && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if ($digits === '') {
            return null;
        }

        return 'tel:' . ($international ? '+' : '') . $digits;
    }

    /**
     * One or two sentences on what the organisation does: the default meta
     * description and the summary in llms.txt.
     * @return string
     */
    public function getDescription(): string
    {
        return (string) config('seo.default_description');
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

        if ($this->getPhoneHref()) {
            $options[] = '<a href="' . e($this->getPhoneHref()) . '">' . e($this->getPhoneNumber()) . '</a>';
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
