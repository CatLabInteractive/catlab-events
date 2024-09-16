<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;

class StatusController
{
    public function status()
    {
        $event = Event::first();

        return new JsonResponse([
            'status' => 'ok'
        ]);
    }
}