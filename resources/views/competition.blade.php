@extends('layouts/front')

@section('title')
    {{ $competition->name }}
@endsection

@section('content')

    <h2>{{ $competition->name }}</h2>

    @if (count($upcoming) > 0)
        <h3>Agenda</h3>
        <table class="table agenda">
            @foreach($upcoming as $v)

                <tr>
                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->startDate->format('d/m/Y H:i') }}
                        </a>
                    </td>

                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->name }}
                        </a>
                    </td>

                    <td>
                        <a href="{{ $v->venue->getLocalUrl() }}">{{ $v->venue->name }}</a>
                    </td>
                </tr>

            @endforeach
        </table>
    @endif

    <h3>Tussenstand</h3>
    <table class="table competition">
        <tr>
            <th>#</th>
            <th>Player</th>
            @foreach($eventDates as $v)
                <th>
                    <a
                        href="{{ $v->event->getUrl() }}"
                    >{{ $v->event->name }} {{$v->startDate->format('d/m')}}</a>
                </th>
            @endforeach
            <th>Seizoenscore</th>
        </tr>

        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            @foreach($eventDates as $v)
                <td style="font-size: 80%;">
                    L: {{ $statistics[$v->id]['limit'] }}, MED: {{ $statistics[$v->id]['average'] }}, D: {{ $statistics[$v->id]['difficulty'] }}
                </td>
            @endforeach

            <td>&nbsp;</td>
        </tr>

        @foreach($groups as $group)
            <tr @if(!$group['valid'])class="invalid"@endif>
                <td>{{ $group['position'] }}</td>
                <td>
                    @if($group['group']->getUrl())
                        <a href="{{ $group['group']->getUrl() }}">{{ $group['group']->name }}</a>
                    @else
                        {{ $group['group']->name }}
                    @endif
                </td>
                @foreach($eventDates as $v)
                    @if(isset($group['events'][$v->id]))
                        <td>
                            <span title="Score: {{ $group['events'][$v->id]['score'] }}">
                                {{ $group['events'][$v->id]['weightedScore'] }}
                                <span class="position">(#{{ $group['events'][$v->id]['position'] }})</span>
                            </span>
                        </td>
                    @else
                        <td>-</td>
                    @endif
                @endforeach
                <td>{{ $group['finalScore'] }}</td>
            </tr>
        @endforeach
    </table>

    <h3>Berekening</h3>
    <p style="font-family: monospace">
        <span title="Quiz moeilijkheid">D<sub>quiz</sub></span> = 0.8 + ((
        <span title="Theoretisch haalbare maximumscore">L<sub>quiz</sub></span> /
        <span title="Gemiddelde van alle behaalde punten">MED(P<sub>quiz</sub>)</span>) / (2 * 5))

        <br>

        <span title="Score van een speler op een quiz">S<sub>speler.quiz</sub></span> =
        (<span title="Aantal behaalde punten op de quiz">P<sub>speler.quiz</sub></span> /
        <span title="Aantal behaalde punten van de winnaar van de quiz">P<sub>winner.quiz</sub></span>) *
        <span title="Quiz moeilijkheid">D<sub>quiz</sub></span> *
        {{ \App\Http\Controllers\CompetitionController::POINT_PER_QUIZ }}

        <br>

        <span title="Seizoenscore speler">R<sub>speler</sub></span> =
        <span title="Hoogst behaalde S-score">S<sub>speler.quiz(max)</sub></span>
        <span title="Op één na hoogst behaalde S-score">S<sub>speler.quiz(max - 1)</sub></span>
    </p>

    <p style="font-family: monospace; font-size: 80%;">
        D: Quiz moeilijkheid<br>
        L: Theoretisch haalbare maximumscore<br>
        P: punten behaald op quiz<br>
        R: Seizoensresultaat
    </p>

@endsection
