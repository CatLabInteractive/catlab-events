@extends('emails/layouts/layout')

@section('content')

    <h2>Leuk dat jullie willen meespelen!</h2>
    <p>Hallo!</p>

    @if($order->play_link)
        <p>
            Klik op de link hieronder om de quiz op te starten:<br />
            {{ $order->play_link }}
        </p>
    @endif

    <p>
        Je mag de link gerust openen, activatie vereist een extra stap.<br />
        Eens geactiveerd kan je de quiz gedurende 72 uur spelen.
    </p>

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
