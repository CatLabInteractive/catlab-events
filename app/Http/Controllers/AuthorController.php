<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Score;
use App\Models\Series;

/**
 * Class CompetitionController
 * @package App\Http\Controllers
 */
class AuthorController
{
    /**
     * @param $personId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($personId)
    {
        /** @var Series $person */
        $person = Person::findOrFail($personId);

        return view(
            'person.view',
            [
                'person' => $person
            ]
        );
    }
}