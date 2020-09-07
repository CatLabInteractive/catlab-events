@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    <h3>Pre-registration / waiting list</h3>

    <table class="table">
    @foreach($waitingList as $user)

        <tr>

            <td>{{ $user['index'] }}</td>
            <td>{{ $user['user']->pivot->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $user['user']->username }}</td>
            <td>{{ $user['user']->email }}</td>
            <td>
                @if($user['group'])
                    Registered with <a href="{{ action('GroupController@show', [ $user['group']->id ]) }}">{{$user['group']->name}}</a>.
                @elseif ($user['user']->pivot->access_token)
                    <a class="btn btn-success btn-sm" href="{{ action('WaitingListController@generateAccessToken', [ $event->id, $user['user']->id ]) }}">Invited</a>
                @else
                    <a class="btn btn-warning btn-sm" href="{{ action('WaitingListController@generateAccessToken', [ $event->id, $user['user']->id ]) }}">Generate invitation</a>
                @endif
            </td>

        </tr>

    @endforeach
    </table>

@endsection
