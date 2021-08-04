@extends('layouts/front')

@section('title')
    Competities
@endsection

@section('content')

    <h2>Competities</h2>
    <p>Sommige quizzen worden gegroepeerd in competities waarbij een competitie-winnaar bepaald wordt.</p>

    @if (count($competitions) > 0)
        <h3>Competities</h3>
        <ul>
            @foreach($competitions as $competition)

                <li>
                    <a href="{{ action('CompetitionController@show', $competition->id) }}">{{ $competition->name }}</a>
                </li>

            @endforeach
        </ul>
    @endif

@endsection
