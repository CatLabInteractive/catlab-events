@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">{{ $event->getOrderLabel() }}</h3>

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(\App\Http\Controllers\EventController::getValidWaitingListToken($event))
        <div class="alert alert-info">
            <p>
                Je staat op de <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a> voor {{ $event->name }}.
                Als er nog tickets vrijgekomen zijn, kan je deze hieronder bestellen.
            </p>
        </div>
    @endif

    @if($availableCategories->count() === 0)

        <div class="alert alert-danger">
            <p>Er zijn op dit moment geen tickets beschikbaar.</p>
        </div>

    @else

        @if($event->doesRequireTeam())
            @include('events/teamsizeWarning', [ 'event' => $event ])
        @endif

        @if(!$user && !$guestCheckout)
            <p>Log in om te bestellen. Je kan inloggen met je e-mailadres; heb je nog geen account, dan maak je er meteen een aan.</p>
            <p><a href="{{ $loginUrl }}" class="btn btn-primary btn-lg">Inloggen en bestellen</a></p>
        @else
        <form method="POST" action="{{ action('EventController@storeRegistration', [ $event->id ]) }}" accept-charset="UTF-8" class="register-one-page">
            @csrf

            {{-- 1. What --}}
            <div class="form-group">
                <label>Kies je ticket</label>

                @foreach($ticketCategories as $category)
                    <div class="radio @if(!$category->isAvailable()) disabled @endif">
                        <label>
                            <input
                                type="radio"
                                name="category"
                                value="{{ $category->id }}"
                                @checked($selectedCategoryId === $category->id)
                                @disabled(!$category->isAvailable())
                            >
                            <strong>{{ $category->name }}</strong>
                            &middot; {{ $category->getFormattedTotalPrice() }}

                            @if($category->eventDates->count() > 0)
                                <br /><small>{{ $category->getDatesForDisplay() }}</small>
                            @endif

                            @foreach ($category->getAvailabilityWarnings() as $warning)
                                <br /><small><strong>{{ $category->errorToString($warning) }}</strong></small>
                            @endforeach

                            @if(!$user && $category->isFree() && $category->isAvailable())
                                <br /><small>Gratis tickets vragen een account: <a href="{{ $loginUrl }}">log in</a>.</small>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>

            {{-- 2. Team --}}
            @if($event->doesRequireTeam())
                @if($user && count($groups) > 0)
                    <div class="form-group">
                        <label for="group">Team</label>
                        <select name="group" id="group" class="form-control">
                            @foreach($groups as $value => $label)
                                <option value="{{ $value }}" @selected((string)old('group') === (string)$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="team">Of schrijf een nieuw team in</label>
                        <input type="text" name="team" id="team" value="{{ $team }}" maxlength="100" class="form-control" placeholder="Teamnaam (laat leeg om het gekozen team in te schrijven)">
                    </div>
                @else
                    <div class="form-group">
                        <label for="team">Teamnaam</label>
                        <input type="text" name="team" id="team" value="{{ $team }}" maxlength="100" class="form-control" required>
                    </div>
                @endif
            @endif

            {{-- 3. Who --}}
            @if($user)
                <p>Je bestelt als <strong>{{ $user->email }}</strong>.</p>
            @else
                <div class="form-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" maxlength="255" class="form-control" autocomplete="email" required>
                    <p class="help-block">Hier sturen we je ticket of je toegang naartoe.</p>
                </div>

                <div class="form-group">
                    <label for="email_confirmation">Herhaal je e-mailadres</label>
                    <input type="email" name="email_confirmation" id="email_confirmation" maxlength="255" class="form-control" autocomplete="email" required>
                </div>
            @endif

            {{-- 4. UiTPAS (optional) --}}
            @if($showUiTPAS)
                <details class="form-group" @if(old('uitpas')) open @endif>
                    <summary>Ik heb een UiTPAS met kansentarief</summary>
                    <p>
                        Geef je UiTPAS kaartnummer in. Wij passen dan het juiste tarief toe.
                        @if(organisation()->getContactOptionsText())
                            <br />Ondervind je problemen? {!! organisation()->getContactOptionsText() !!}
                        @endif
                    </p>
                    <input type="text" name="uitpas" value="{{ old('uitpas') }}" maxlength="64" class="form-control" placeholder="UiTPAS kaartnummer">
                </details>
            @endif

            @if($event->venue)
                <p class="small">
                    Bij dit evenement worden foto- en video-opnames gemaakt. Wanneer je deelneemt, geef je de organisatie
                    toestemming om beelden waar je op staat te gebruiken voor communicatiedoeleinden.
                </p>
            @endif

            @if($recaptchaSiteKey)
                <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                </div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @endif

            <p>
                <input type="submit" value="Betalen" class="btn btn-primary btn-lg">
            </p>

            <p class="small">
                De betaling verloopt via de beveiligde betaalpagina van
                <a href="{{ config('app.owner.url') }}" target="_blank">{{ config('app.owner.name') }}</a>.
                Onbetaalde bestellingen vervallen na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten.
                Zie ook ons <a href="{{ action('DocumentController@privacy') }}">privacybeleid</a> en de
                <a href="{{ action('DocumentController@tos') }}">gebruiksvoorwaarden</a>.
            </p>
        </form>

        @if(!$user)
            <p>Heb je al een account? <a href="{{ $loginUrl }}">Log in</a>.</p>
        @endif
        @endif

    @endif

@endsection
