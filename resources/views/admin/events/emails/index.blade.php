@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>E-mails</h3>

    <p>
        Kies per mail welke versie dit evenement verstuurt: de standaard van de organisatie,
        een sjabloon, of een eigen tekst voor dit evenement.
    </p>

    <table class="table">
        <thead>
            <tr>
                <th>Mail</th>
                <th>Versie</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($mails as $type => $mail)
                <tr>
                    <td><strong>{{ $mail['label'] }}</strong></td>
                    <td>{{ $mail['origin'] }}</td>
                    <td class="text-right" style="white-space: nowrap;">
                        <a class="btn btn-sm btn-primary" href="{{ action('Admin\EventEmailController@edit', [ $event->id, $type ]) }}">Aanpassen</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
