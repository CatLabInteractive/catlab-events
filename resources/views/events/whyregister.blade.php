@extends('layouts/register')

@section('title')
    Registreren voor {{ $event->name }}
@endsection

@section('register-content')

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">Registreren</h3>

    <div class="row">
        <div class="col-md-6">
            <h3>Inschrijven</h3>
            <p>
                Om onze evenementen vlot te laten verlopen is inschrijven enkel mogelijk via deze website.
                Je inschrijving dient meteen online betaald te worden; hou dus je bankkaart,
                kredietkaart of paypal account bij de hand.
            </p>

            <p>
                <a href="{{ $loginLink  }}" class="btn btn-success"><i class="glyphicon glyphicon-user"></i> Inloggen & inschrijven</a>
            </p>
        </div>

        <div class="col-md-6">

            <h3>Privacy & veiligheid</h3>
            <p>
                De betaling verloopt via de beveiligde betaalterminal van <a href="{{ config('app.owner.url') }}" target="_blank">{{ config('app.owner.name') }}</a>.<br />
                {{ config('app.owner.name') }} is gebonden aan hun <a href="{{ action('DocumentController@privacy') }}">Privacy beleid</a> en
                <a href="{{ action('DocumentController@tos') }}">Gebruiksvoorwaarden</a>.
                @if(organisation()->getContactOptionsText())
                    Heb je vragen of opmerkingen? {!! organisation()->getContactOptionsText() !!}
                @endif
            </p>

            <p>
                <a href="mailto:hallo@quizfabriek.be" class="btn btn-danger" target="_blank"><i class="glyphicon glyphicon-envelope"></i> Contact opnemen</a>
            </p>

        </div>
    </div>

@endsection
