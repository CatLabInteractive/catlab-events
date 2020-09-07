@extends('layouts/front')

@section('content')

    <h2>Agenda</h2>
    @if (count($events) === 0)
        <p>Er zijn nog geen evenementen gepland.
    @else
        <table class="table">
            @foreach($events as $v)

                <tr>
                    <td>
                        {{ $v->startDate->format('d/m/Y H:i') }}
                    </td>

                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->name }}
                        </a>
                    </td>

                    <td>
                        {{ $v->venue->name }}
                    </td>
                </tr>

            @endforeach
        </table>
    @endif

@endsection