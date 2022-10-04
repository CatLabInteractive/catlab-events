@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <h3>Pre-registration / waiting list</h3>

    <table class="table">
        @foreach($waitingList as $listItem)
            <tr>
                <td>{{$listItem['index']}}</td>
                <td>{{$listItem['user']->pivot->created_at->format('d/m/Y H:i')}}</td>
                <td>{{$listItem['user']->name}}</td>
                <td>{{$listItem['user']->email}}</td>
                <td>{{$listItem['url']}}</td>
            </tr>
        @endforeach
    </table>

@endsection
