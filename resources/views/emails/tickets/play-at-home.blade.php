@extends('emails/layouts/layout')

@section('content')

    <h2>Dank voor je aankoop!</h2>
    <p>
        Beste leden van {{ $group->name }},
    </p>

    <p>
        Super tof dat jullie ons thuispakket {{ $event->name }} gekocht hebben!
    </p>

    <p>
        We zijn de quiz nog vollop aan het voorbereiden, maar vanaf {{ $event->startDate->format('H:i') }} mogen jullie
        een mailtje van ons verwachten met de link en de instructies om de quiz thuis te spelen.
    </p>

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
