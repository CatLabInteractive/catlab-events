@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <h3>Scores</h3>
    <table class="table">
        @foreach($scores as $score)

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


@endsection
