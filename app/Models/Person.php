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

use CatLab\CentralStorage\Client\Models\Asset;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Class Person
 * @package App\Models
 */
class Person extends Model
{
    const ROLE_PRODUCER = 'producer';
    const ROLE_AUTHOR = 'author';
    const ROLE_PRESENTER = 'presenter';
    const ROLE_MUSICIAN = 'musician';
    const ROLE_TECHNICIAN = 'technicians';

    use SoftDeletes;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function events()
    {
        return $this->belongsToMany(Event::class)->withPivot('role');
    }

    /**
     * Get a grouped list of PersonEvents
     * @return PersonEvent[]
     */
    public function getPersonEvents()
    {
        /** @var PersonEvent[] $events */
        $events = [];
        foreach ($this->events()->get() as $event) {
            if (!isset($events[$event->id])) {
                $events[$event->id] = new PersonEvent($event);
            }
            $events[$event->id]->addRole($event->pivot->role);
        }
        return collect(array_values($events));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('active', '=', 1);
    }

    public function getUrl()
    {
        return action('AuthorController@view', [ $this->id, Str::slug($this->getNameAttribute()) ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Event|null
     */
    public function getNextEvent()
    {
        return $this->events()
            ->upcoming()
            ->orderByStartDate()
            ->first();
    }

    /**
     * @return string
     */
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function photo()
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
    }

    /**
     * @return array
     */
    public function getJsonLD()
    {
        return [
            "@type" => "Person",
            "name" => $this->name,
            "givenName" => $this->first_name,
            "familyName" => $this->last_name
        ];
    }
}
