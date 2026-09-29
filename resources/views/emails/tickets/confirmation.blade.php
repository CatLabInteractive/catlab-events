@extends('emails/layouts/layout')

@section('content')

    <h2>We zijn er bij!</h2>
    @if ($group)
        <p>
            Beste leden van {{ $group->name }},
        </p>
    @else
        <p>Hallo!</p>
    @endif

    <p>
        We zijn er bij!
    </p>

    @include('emails.blocks.eventDates')

    <p>
        Onze quizzen worden steevast digitaal gespeeld, waarbij de deelnemers antwoorden met een zelf meegebrachte tablet,
        of een smartphone. Met een smartphone werkt het even goed als met een tablet, maar een tablet is groter en wat
        handiger om samen te bekijken.
    </p>

    <h3>Voorbereiding</h3>

    <ul>
        @if($group)
            <li>
                Stel de leden van je team in op de <a href="{{ action('GroupController@show', [ $group->id ]) }}">{{ $group->name }} team pagina</a>.
            </li>
        @endif

        <li>
            Like onze <a href="https://www.facebook.com/quizfabriek/">facebook pagina</a> voor de laatste nieuwtjes.
        </li>

        <li>
            Breng per team een opgeladen tablet of smartphone mee.
        </li>
    </ul>

    @include('emails.blocks.location')

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
