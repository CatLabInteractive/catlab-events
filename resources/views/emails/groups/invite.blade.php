@extends('emails/layouts/layout')

@section('content')

    <h2>Wij willen jou!</h2>
    <p>
        Dag {{ $invitation->name }},
    </p>

    <p>
        {{ $from->username }} heeft je toegevoegd aan het team "{{ $group->name }}"
    </p>

    <p>
        Klik op de onderstaande link om je lidmaatschap te accepteren.
    </p>

    @include('emails.blocks.button', [ 'url' => $inviteUrl, 'label' => 'Accepteren' ])

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection