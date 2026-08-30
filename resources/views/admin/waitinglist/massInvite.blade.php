@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>Meerdere mensen uitnodigen</h3>

    <table class="table" style="max-width: 500px;">
        @if($hasFiniteTickets)
            <tr>
                <td>Vrije tickets (pending bestellingen meegerekend)</td>
                <td style="text-align: right;">{{ $available }}</td>
            </tr>
            <tr>
                <td>Openstaande uitnodigingen</td>
                <td style="text-align: right;">&minus; {{ $outstanding }}</td>
            </tr>
            <tr>
                <th>Voorgesteld</th>
                <th style="text-align: right;">{{ $proposed }}</th>
            </tr>
        @else
            <tr>
                <td>Dit event heeft een onbeperkt aantal tickets.</td>
                <td style="text-align: right;">{{ $proposed }} te versturen</td>
            </tr>
        @endif
    </table>

    @if($eligible->count() === 0)
        <p class="alert alert-info">
            Er is niemand meer op de wachtlijst die uitgenodigd kan worden.
            ({{ $attending }} al geregistreerd, {{ $outstanding }} al uitgenodigd.)
        </p>

        <p>
            <a class="btn btn-secondary" href="{{ action('Admin\WaitingListController@index', [ $event->id ]) }}">Terug</a>
        </p>
    @else
        <form action="{{ action('Admin\WaitingListController@sendMassInvite', [ $event->id ]) }}" method="post">
            {{ csrf_field() }}

            <div class="form-group" style="max-width: 300px;">
                <label for="amount">Aantal te versturen</label>
                <input type="number" class="form-control" id="amount" name="amount"
                       min="1" max="{{ $eligible->count() }}" value="{{ $proposed }}" />
                <small class="text-muted">
                    Ze worden verstuurd naar de mensen die het langst op de wachtlijst staan.
                </small>
            </div>

            <p>
                <button type="submit" class="btn btn-success">Verstuur uitnodigingen</button>
                <a class="btn btn-secondary" href="{{ action('Admin\WaitingListController@index', [ $event->id ]) }}">Annuleer</a>
            </p>
        </form>

        <h4>Wie komt in aanmerking ({{ $eligible->count() }})</h4>

        <p class="text-muted">
            {{ $attending }} overgeslagen: al geregistreerd.
            {{ $outstanding }} overgeslagen: al uitgenodigd.
        </p>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Op wachtlijst sinds</th>
                    <th>Gebruiker</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
            @foreach($eligible as $index => $user)
                <tr @if($index >= $proposed) class="text-muted" @endif>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->pivot->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->email }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
