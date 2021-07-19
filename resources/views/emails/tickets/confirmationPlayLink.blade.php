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
        Eens je de link volgt en de quiz activeert, heb je 72 uur de tijd om de quiz spelen. Daarna vervalt de link.
    </p>

    <p>
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection
