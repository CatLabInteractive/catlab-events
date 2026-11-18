@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    @foreach($event->eventDates as $eventDate)
        @if($eventDate->hasScores())
            <h3>Eindstand {{ $eventDate->startDate->translatedFormat('j F Y') }}</h3>
            <table class="table kv-scores">
                @foreach($eventDate->scores as $score)

                    <tr>
                        <td>{{ $score->position }}</td>
                        <td>
                            @if($score->group)
                                @if ($score->name !== $score->group->name)
                                    {{ $score->name }} (<a href="{{ $score->group->getUrl() }}">{{ $score->group->name }}</a>)
                                @else
                                    <a href="{{ $score->group->getUrl() }}">{{ $score->group->name }}</a>
                                @endif
                            @else
                                {{ $score->name }}
                            @endif

                        </td>
                        <td>{{ $score->score }}</td>
                    </tr>

                @endforeach
            </table>
        @endif
    @endforeach


@endsection
