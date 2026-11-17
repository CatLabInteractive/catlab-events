@extends('layouts/admin')

@section('content')

    <h2>E-mails</h2>

    @include('admin.emails.partials.tabs', [ 'active' => 'standard' ])

    <p>
        Deze mails worden automatisch verstuurd. Bekijk hoe ze eruit zien, of pas ze aan voor alle
        evenementen van je organisatie.
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
            @foreach($types as $type => $definition)
                <tr>
                    <td>
                        <strong>{{ $definition['label'] }}</strong><br />
                        <small class="text-muted">{{ $definition['description'] }}</small>
                    </td>
                    <td>
                        @if($definition['customised'])
                            Aangepast
                            @if($definition['updated_at'])
                                <br /><small class="text-muted">{{ $definition['updated_at']->format('d/m/Y H:i') }}</small>
                            @endif
                        @else
                            Standaard
                        @endif
                    </td>
                    <td class="text-right" style="white-space: nowrap;">
                        <a class="btn btn-sm btn-secondary" href="{{ action('Admin\EmailTemplateController@show', [ $type ]) }}">Voorbeeld</a>
                        <a class="btn btn-sm btn-primary" href="{{ action('Admin\EmailTemplateController@edit', [ $type ]) }}">Aanpassen</a>
                        @if($definition['customised'])
                            <form action="{{ action('Admin\EmailTemplateController@reset', [ $type ]) }}" method="post"
                                  style="display: inline-block;"
                                  onsubmit="return confirm('De aangepaste versie wordt verwijderd en de standaardversie wordt weer verstuurd. Doorgaan?');">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-sm btn-danger">Terug naar standaard</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
