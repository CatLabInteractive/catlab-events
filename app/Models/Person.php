<?php

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
    const ROLE_AUTHOR = 'author';
    const ROLE_PRESENTER = 'presenter';
    const ROLE_MUSICIAN = 'musician';

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
            ->orderBy('startDate', 'asc')
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
