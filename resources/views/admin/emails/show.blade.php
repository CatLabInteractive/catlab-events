@extends('layouts/admin')

@section('content')

    <h2>{{ $label }}</h2>

    <p>{{ $description }}</p>

    <p class="alert alert-info">
        @if($customised)
            Je organisatie gebruikt een aangepaste versie van deze mail.
        @else
            Je organisatie gebruikt de standaardversie van deze mail.
        @endif
        Het voorbeeld hieronder gebruikt verzonnen gegevens.
    </p>

    <table class="table">
        <tr>
            <th style="width: 120px;">Onderwerp</th>
            <td>{{ $subject }}</td>
        </tr>
    </table>

    <iframe src="{{ action('Admin\EmailTemplateController@preview', [ $type ]) }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd;"
            sandbox=""
            title="Voorbeeld van de mail"></iframe>

    <p style="margin-top: 15px;">
        <a class="btn btn-primary" href="{{ action('Admin\EmailTemplateController@edit', [ $type ]) }}">Aanpassen</a>
        <a class="btn btn-secondary" href="{{ action('Admin\EmailTemplateController@index') }}">Terug</a>
    </p>

    @if($customised)
        <form action="{{ action('Admin\EmailTemplateController@reset', [ $type ]) }}" method="post"
              onsubmit="return confirm('De aangepaste versie wordt verwijderd en de standaardversie wordt weer verstuurd. Doorgaan?');">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-danger">Terug naar standaard</button>
        </form>
    @endif

@endsection
