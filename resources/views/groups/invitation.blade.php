@extends('layouts/front')

@section('title')
    {{ $invitation->group->name }} Uitnodiging
@endsection

@section('content')

    <div class="invoice">

        <h3>Dag {{ $invitation->name }}</h3>

        <p>
            Je bent uitgenodigd om deel te worden van het team <strong>{{ $invitation->group->name }}</strong>.
        </p>

        <p>Klik op de onderstaande link om de uitnodiging te aanvaarden.</p>

        <p>
            <a href="{{ action('GroupController@acceptInvitation', [ $invitation->id, $invitation->token ]) }}" class="btn btn-primary">Accepteren</a>
        </p>

    </div>

@endsection