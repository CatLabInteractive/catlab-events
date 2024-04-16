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
}} en jij staat op de wachtlijst.

Ben je nog geïnteresseerd in het ticket? Bestel het dan via
{{ $url }}

Wees er snel bij, want we hebben dit mailtje naar enkele mensen gestuurd.

Toch geen interesse? Stuur ons dan een mailtje terug, zodat wij de volgende kunnen uitnodigen.

Veel succes!
</pre>

@endsection
