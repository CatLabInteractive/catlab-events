@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <h3>Pre-registration / waiting list</h3>

    <p class="alert alert-info">
        Je kan deze template gebruiken om de persoon op de wachtlijst te contacteren.
        Stuur daarom een email naar: <code><a href="mailto:{{$user->email}}">{{ $user->email }}</a></code>.
    </p>

    <p class="alert alert-danger">
        Deze email is nog niet verstuurd! Je dient deze manueel te versturen.
    </p>

    <pre>{{ $user->email }}</pre>

    <pre>Wachtlijst {{ $event->name }}</pre>

<pre>
Beste {{ $user->username }},

Er is een ticket vrijgekomen voor {{ $event->name }} op {{ $event->eventDates
        ->filter(function($date) { return !$date->isSoldOut(); })
        ->map(function($date) { return $date->startDate->format('d/m/Y'); })
        ->join(' & ')
}} en jij staat als volgende op de wachtlijst.

Ben je nog geïnteresseerd in het ticket? Bestel het dan snel via
{{ $url }}

Wees er snel bij, want als je het niet binnen de 24u bestelt gaat het naar de volgende.
Toch geen interesse? Stuur ons dan een mailtje terug, zodat wij de volgende kunnen uitnodigen.

Veel succes!
</pre>

@endsection
