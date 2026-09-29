@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>Gasten</h3>

    <p>
        Schrijf gasten in zonder dat ze een account moeten aanmaken. Ze krijgen
        een gratis ticket in de gekozen categorie en tellen mee als verkocht ticket.
    </p>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($soldOut)
        <p class="alert alert-warning">
            Dit event is uitverkocht. Een gast komt er bovenop.
        </p>
    @endif

    @if(count($ticketCategories) === 0)
        <p class="alert alert-info">
            Dit event heeft nog geen ticketcategorieën. Maak er eerst een aan.
        </p>
    @else
        <form action="{{ action('Admin\GuestRegistrationController@store', [ $event->id ]) }}" method="post" style="max-width: 500px;">
            {{ csrf_field() }}

            <div class="form-group">
                <label for="name">{{ $event->doesRequireTeam() ? 'Teamnaam' : 'Naam' }}</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="255" required value="{{ old('name') }}" />
            </div>

            <div class="form-group">
                <label for="ticketCategory">Ticketcategorie</label>
                <select class="form-control" id="ticketCategory" name="ticketCategory">
                    @foreach($ticketCategories as $ticketCategory)
                        <option value="{{ $ticketCategory->id }}" @if(old('ticketCategory') == $ticketCategory->id) selected @endif>{{ $ticketCategory->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="email">E-mailadres (optioneel)</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="255" value="{{ old('email') }}" />
                <small class="form-text text-muted">
                    Als je een adres invult, krijgt de gast de gewone bevestigingsmail (met speellink, als die er is).
                </small>
            </div>

            <div class="form-group">
                <label for="notes">Notities (optioneel)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
            </div>

            <p>
                <button type="submit" class="btn btn-success">Inschrijven</button>
            </p>
        </form>
    @endif

    @if(count($orders) > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>{{ $event->doesRequireTeam() ? 'Team' : 'Naam' }}</th>
                    <th>Ticketcategorie</th>
                    <th>Email</th>
                    <th>Notities</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($orders as $order)
                <tr>
                    <td>{{ $order->getAttendeeName() }}</td>
                    <td>{{ $order->ticketCategory ? $order->ticketCategory->name : '' }}</td>
                    <td>{{ $order->guest_email }}</td>
                    <td>{{ $order->guest_notes }}</td>
                    <td>
                        {{ $order->state }}
                        @if($order->play_link)
                            <br /><a href="{{ $order->play_link }}" target="_blank" rel="noopener">speellink</a>
                        @endif
                    </td>
                    <td>
                        @if(!$order->isCancelled())
                            <form action="{{ action('Admin\GuestRegistrationController@cancel', [ $event->id, $order->id ]) }}" method="post"
                                  onsubmit="return confirm('Deze gast annuleren?');">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-danger btn-sm">Annuleren</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
