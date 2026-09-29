@extends('emails/layouts/layout')

@section('content')

    <h2>Er is een ticket vrijgekomen!</h2>

    <p>
        Beste {{ $user->username }},
    </p>

    @php
        $availableDates = $event->eventDates
            ->filter(function($date) { return !$date->isSoldOut(); })
            ->map(function($date) { return $date->startDate->format('d/m/Y'); })
            ->join(' & ');
    @endphp

    <p>
        Er is een ticket vrijgekomen voor {{ $event->name }}@if($availableDates) op {{ $availableDates }}@endif
        en jij staat op de wachtlijst.
    </p>

    <p>
        Ben je nog geïnteresseerd in het ticket? Bestel het dan via de onderstaande knop.
    </p>

    @include('emails.blocks.button', [ 'url' => $url, 'label' => 'Bestel je ticket' ])

    <p>
        Werkt de knop niet? Gebruik dan deze link:<br />
        <a href="{{ $url }}">{{ $url }}</a>
    </p>

    <p>
        Wees er snel bij, want we hebben dit mailtje naar enkele mensen gestuurd.
    </p>

    <p>
        Toch geen interesse? Stuur ons dan een mailtje terug, zodat wij de volgende kunnen uitnodigen.
    </p>

    <p>
        Veel succes!<br />
        De Quizfabriek
    </p>

@endsection
