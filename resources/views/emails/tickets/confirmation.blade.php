@extends('emails/layouts/layout')

@section('content')

    <h2>We zijn er bij!</h2>
    <p>
        Beste leden van {{ $group->name }},
    </p>

    <p>
        We zijn er bij!
    </p>

    @if($event->venue)
        <p>
            Op <strong>{{ $event->startDate->format('d/m/Y') }}</strong> gaan we naar
            <strong>{{ $event->venue->name }}</strong> om deel te nemen aan <strong>{{ $event->name }}</strong>.
        </p>
    @else
        <p>
            Op <strong>{{ $event->startDate->format('d/m/Y') }}</strong> spelen we <strong>{{ $event->name }}</strong>.
        </p>
    @endif

    @if($event->doorsDate)
        <p>
            Aanmelden kan vanaf <strong>{{ $event->doorsDate->format('H:i') }}</strong>, de quiz zelf start stipt om
            {{ $event->startDate->format('H:i') }}.
        </p>
    @else
        <p>
            De quiz start stipt om <strong>{{ $event->startDate->format('H:i') }}</strong>, meld je daarom zeker voor {{ $event->startDate->format('H:i') }} aan.
        </p>
    @endif

    <h3>Voorbereiding</h3>
    <ul>
        <li>
            Stel de leden van je team in op de <a href="{{ action('GroupController@show', [ $group->id ]) }}">{{ $group->name }} team pagina</a>.
        </li>

        <li>
            Like onze <a href="https://www.facebook.com/quizfabriek/">facebook pagina</a> voor de laatste nieuwtjes.
        </li>

        <li>
            Zorg ervoor dat je tablet (1 per team) opgeladen is.
        </li>
    </ul>

    @if($event->venue)
        <p>De quiz gaat door in:</p>
        <p>
            <strong>{{ $event->venue->name }}</strong><br>
            {{ $event->venue->address }}<br>
            {{ $event->venue->city }}
        </p>

        <p>
            Meld je voor {{ $event->startDate->format('H:i') }} aan bij de inschrijvingstafel. Daar ontvang je de (geheime)
            team-code. Deze code is strikt persoonlijk; laat hem niet aan de andere teams zien. Daarna mag je zelf
            een tafeltje kiezen.
        </p>
    @elseif($event->getLiveStreamUrl())
        <p>
            We spelen de quiz online via {{ $event->getLiveStreamUrl() }}. Je krijgt nog een afzonderlijke mail met
            je persoonlijke code die je nodig hebt om deel te nemen.
        </p>
    @endif

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
