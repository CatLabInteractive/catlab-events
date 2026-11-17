@extends('layouts/admin')

@section('content')

    <h2>E-mails</h2>

    @include('admin.emails.partials.tabs', [ 'active' => 'library' ])

    @if(Session::has('error'))
        <div class="alert alert-danger">{{ Session::get('error') }}</div>
    @endif

    <p>
        Een sjabloon is een mail met een naam die je bij meerdere evenementen kan kiezen.
    </p>

    <p>
        <a class="btn btn-success" href="{{ action('Admin\EmailLibraryController@create') }}">Nieuw sjabloon</a>
    </p>

    <table class="table">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Mail</th>
                <th>Aangepast op</th>
                <th>Gebruikt door</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $template)
                <tr>
                    <td><strong>{{ $template->name }}</strong></td>
                    <td>{{ $labels[$template->type] ?? $template->type }}</td>
                    <td>{{ $template->updated_at ? $template->updated_at->format('d/m/Y H:i') : '' }}</td>
                    <td>
                        @forelse($events[$template->id] as $eventId => $name)
                            <a href="{{ action('Admin\EventEmailController@index', [ $eventId ]) }}">{{ $name }}</a>@if(!$loop->last), @endif
                        @empty
                            <span class="text-muted">Nog niet gebruikt</span>
                        @endforelse
                    </td>
                    <td class="text-right" style="white-space: nowrap;">
                        <a class="btn btn-sm btn-primary" href="{{ action('Admin\EmailLibraryController@edit', [ $template->id ]) }}">Aanpassen</a>
                        <form action="{{ action('Admin\EmailLibraryController@destroy', [ $template->id ]) }}" method="post" style="display: inline;"
                              onsubmit="return confirm('Dit sjabloon verwijderen?');">
                            {{ csrf_field() }}
                            <button type="submit" class="btn btn-sm btn-danger">Verwijderen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-muted">Je hebt nog geen sjablonen.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

@endsection
