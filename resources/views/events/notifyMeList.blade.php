@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <div class="alert alert-warning">
        <p>
            Er zijn nog geen tickets beschikbaar voor <strong>{{ $event->name }}</strong>.
            @if($startDate)
                <br />De ticketverkoop start op {{ $startDate->formatLocalized('%A %d %B %Y, %H:%M') }}.
            @endif
        </p>
    </div>


    <h3>Hou me op de hoogte</h3>
    @if($startDate)
        <p>
            Door je nu al te registreren maak je het meeste kans om een ticket te bemachtigen,
            zo verlies je op {{ $startDate->formatLocalized('%A %-d %B') }} geen enkel moment en
            heb je in geen tijd een ticket in handen. Wij sturen je een dag voor de start van de ticketverkoop
            een herinneringsmailtje.
        </p>

        <div class="alert alert-info">
            <p>
                <strong>Een plaats op de pre-registratielijst geeft geen voorrang op de normale
                    inschrijvingsprocedure.</strong> Wil je er graag bij zijn, zorg er dan voor dat je om
                {{ $startDate->formatLocalized('%H:%M') }} aan je computer zit en ingelogd bent.
                Toch geen ticket kunnen bemachtigen? Wie op deze lijst staat komt ook meteen in de wachtlijst terecht.
                Geannuleerde of extra tickets worden eerst via de wachtlijst aangeboden, in volgorde van inschrijfdatum.
            </p>
        </div>
    @else
        <p>Door je nu al te registreren houden we jou op de hoogte over de start van de ticketverkoop.</p>
    @endif

    @if($goingGroup)

        <div class="alert alert-warning">
            <p>
                Je hebt al tickets voor team "{{ $goingGroup->name }}".
            </p>
        </div>

    @elseif($waitingListItem)

        <div class="alert alert-success">
            <p>
                Je staat op de pre-registratielijst sinds {{ $waitingListItem->pivot->created_at->formatLocalized('%A %d %B %Y, %H:%M') }}.
            </p>
        </div>

    @else

        <form method="get" action="{{ action('WaitingListController@addToWaitinglist', [ $event->id ]) }}">

            <button class="btn btn-success">Pre-registreren</button>

        </form>

    @endif

    @if($adminLink)
        <a href="{{$adminLink}}" class="btn btn-default">Lijst bekijken</a>
    @endif


@endsection
