@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>Uitnodiging versturen</h3>

    @if($user->pivot->invitation_sent_at)
        <p class="alert alert-warning">
            Deze persoon kreeg al een uitnodiging op
            {{ \Carbon\Carbon::parse($user->pivot->invitation_sent_at)->format('d/m/Y H:i') }}.
            Versturen stuurt dezelfde link opnieuw.
        </p>
    @endif

    <table class="table">
        <tr>
            <th style="width: 120px;">Aan</th>
            <td>{{ $user->username }} &lt;{{ $user->email }}&gt;</td>
        </tr>
        <tr>
            <th>Onderwerp</th>
            <td>{{ $subject }}</td>
        </tr>
        <tr>
            <th>Link</th>
            <td>
                @if($user->pivot->access_token)
                    <code>{{ $url }}</code>
                @else
                    <em>Link nog niet aangemaakt</em>
                    <br />
                    <small class="text-muted">
                        De persoonlijke link wordt pas aangemaakt op het moment dat je verstuurt of genereert.
                    </small>
                @endif
            </td>
        </tr>
    </table>

    <iframe srcdoc="{{ $body }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd;"
            sandbox=""
            title="Voorbeeld van de uitnodiging"></iframe>

    <form action="{{ action('Admin\WaitingListController@sendInvite', [ $event->id, $user->id ]) }}" method="post">
        {{ csrf_field() }}

        <p style="margin-top: 15px;">
            <button type="submit" class="btn btn-success">Verstuur uitnodiging</button>
            <button type="submit" class="btn btn-primary"
                    formaction="{{ action('Admin\WaitingListController@generateInvite', [ $event->id, $user->id ]) }}">
                Genereer zonder versturen
            </button>
            <a class="btn btn-secondary" href="{{ action('Admin\WaitingListController@index', [ $event->id ]) }}">Annuleer</a>
        </p>
        <p>
            <small class="text-muted">
                "Genereer zonder versturen" maakt de persoonlijke link aan en toont de mail zodat je de tekst
                kan aanpassen voor je hem verstuurt, of hem kan kopiëren naar je eigen mailprogramma.
            </small>
        </p>
    </form>

@endsection
