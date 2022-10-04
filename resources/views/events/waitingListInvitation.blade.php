@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <h3>Pre-registration / waiting list</h3>

    <p>Mail this to <code><a href="mailto:{{$user->email}}">{{ $user->email }}</a></code>.</p>
    <pre>Wachtlijst {{ $event->name }}</pre>

<pre>
Beste {{ $user->username }},

Er is een ticket vrijgekomen voor {{ $event->name }} op {{ $event->startDate->format('m/d/Y') }} en jij staat als volgende op de wachtlijst.

Ben je nog geïnteresseerd in het ticket? Bestel het dan snel via
{{ $url }}

Wees er snel bij, want als je het niet binnen de 24u besteld gaat het naar de volgende.
Toch geen interesse? Stuur ons dan een mailtje terug, zodat wij de volgende kunnen uitnodigen.

Veel succes!
</pre>

@endsection
