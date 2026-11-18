@extends('layouts/front')

@section('title')
    Competities
@endsection

@section('content')

    <p class="kv-lead">Sommige quizzen worden gegroepeerd in competities waarbij een competitie-winnaar bepaald wordt.</p>

    @if (count($competitions) > 0)
        <ul class="kv-list">
            @foreach($competitions as $competition)

                <li>
                    <a href="{{ action('CompetitionController@show', $competition->id) }}">{{ $competition->name }}</a>
                </li>

            @endforeach
        </ul>
    @endif

@endsection
