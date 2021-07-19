@extends('emails/layouts/layout')

@section('content')

    <h2>Inschrijving geannuleerd</h2>
    @if($group)
    <p>
        Beste leden van {{ $group->name }},
    </p>
    @else
        <p>
            Hallo!
        </p>
    @endif

    <p>
        Je inschrijving voor <strong>{{ $event->name }}</strong> is geannuleerd en je inschrijvingsgeld
        is terugbetaald.
    </p>

    <p>Als dit een fout is, neem dan contact op met hallo@quizfabriek.be.</p>

    <p>
        Tot een andere keer!<br />
        De Quizfabriek
    </p>

@endsection
