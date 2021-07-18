@extends('emails/layouts/layout')

@section('content')

    <h2>Leuk dat jullie willen meepspelen!</h2>
    <p>Hallo!</p>

    @if($order->play_link)
        <p>
            Klik op de link hieronder om de quiz op te starten:<br />\
            {{ $order->play_link }}
        </p>
    @endif

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
