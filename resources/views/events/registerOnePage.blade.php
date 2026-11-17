@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

<div class="rf">

    <h2 class="rf-title">{{ $event->getOrderLabel() }}</h2>

    @if(!$errors->isEmpty())
        <div class="rf-alert rf-alert-error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(\App\Http\Controllers\EventController::getValidWaitingListToken($event))
        <div class="rf-alert">
            Je staat op de <a href="{{ action('WaitingListController@waitingList', [ $event->id ]) }}">wachtlijst</a> voor {{ $event->name }}.
            Als er nog tickets vrijgekomen zijn, kan je deze hieronder bestellen.
        </div>
    @endif

    @if($availableCategories->count() === 0)

        <div class="rf-alert rf-alert-error">Er zijn op dit moment geen tickets beschikbaar.</div>

    @elseif(!$user && !$guestCheckout)

        <p>Log in om te bestellen. Je kan inloggen met je e-mailadres; heb je nog geen account, dan maak je er meteen een aan.</p>
        <a href="{{ $loginUrl }}" class="rf-button">Inloggen en bestellen</a>

    @else

        {{-- Returning teams: their history lives on the existing team, which needs a login. --}}
        @if(!$user && $event->doesRequireTeam())
            <div class="rf-returning register-returning-team">
                <p>
                    <strong>Speelde je team al eerder mee?</strong>
                    Log dan eerst in en kies je bestaande team, zodat jullie scores en historiek bij elkaar blijven.
                </p>
                <a href="{{ $loginUrl }}" class="rf-button rf-button-secondary">Inloggen en mijn team kiezen</a>
                <p class="rf-returning-first">Eerste keer? Vul hieronder gewoon alles in, een account is niet nodig.</p>
            </div>
        @endif

        <form method="POST" action="{{ action('EventController@storeRegistration', [ $event->id ]) }}" accept-charset="UTF-8" class="register-one-page">
            @csrf

            {{-- 1. What --}}
            <fieldset class="rf-step">
                <legend class="rf-step-title">Kies je ticket</legend>

                <div class="rf-choices">
                    {{-- Only tickets that can be bought now: sold out or closed ones only confuse. --}}
                    @foreach($availableCategories as $category)
                        <label class="rf-choice">
                            <input
                                type="radio"
                                name="category"
                                value="{{ $category->id }}"
                                class="rf-choice-input"
                                @checked($selectedCategoryId === $category->id)
                            >
                            <span class="rf-choice-body">
                                <span class="rf-choice-head">
                                    <span class="rf-choice-name">{{ $category->name }}</span>
                                    <span class="rf-choice-price">{{ $category->getFormattedTotalPrice() }}</span>
                                </span>

                                @if($category->eventDates->count() > 0)
                                    <span class="rf-choice-meta">{{ $category->getDatesForDisplay() }}</span>
                                @endif

                                @foreach ($category->getAvailabilityWarnings() as $warning)
                                    <span class="rf-choice-warning">{{ $category->errorToString($warning) }}</span>
                                @endforeach

                                @if(!$user && $category->isFree())
                                    <span class="rf-choice-meta">Gratis tickets vragen een account: <a href="{{ $loginUrl }}">log in</a>.</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- 2. Team --}}
            @if($event->doesRequireTeam())
                <fieldset class="rf-step">
                    <legend class="rf-step-title">Je team</legend>

                    @if($user && count($groups) > 0)
                        <div class="rf-field">
                            <label for="group" class="rf-label">Kies een van je teams</label>
                            <select name="group" id="group" class="rf-input">
                                @foreach($groups as $value => $label)
                                    <option value="{{ $value }}" @selected((string)old('group') === (string)$value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rf-field">
                            <label for="team" class="rf-label">Of schrijf een nieuw team in <span class="rf-optional">optioneel</span></label>
                            <input type="text" name="team" id="team" value="{{ $team }}" maxlength="100" class="rf-input @error('team') rf-input-error @enderror" autocomplete="off" aria-describedby="team-help">
                            <p class="rf-help" id="team-help">Laat leeg om het gekozen team in te schrijven.</p>
                        </div>
                    @else
                        <div class="rf-field">
                            <label for="team" class="rf-label">Teamnaam</label>
                            <input type="text" name="team" id="team" value="{{ $team }}" maxlength="100" class="rf-input @error('team') rf-input-error @enderror" autocomplete="off" required aria-describedby="team-help">
                            <p class="rf-help" id="team-help">
                                @if($event->team_size)
                                    Je speelt in een team van maximaal {{ $event->team_size }} personen.
                                @else
                                    Speel je alleen? Verzin dan toch een unieke naam, die gebruiken we in het spel.
                                @endif
                            </p>
                        </div>
                    @endif
                </fieldset>
            @endif

            {{-- 3. Who --}}
            <fieldset class="rf-step">
                <legend class="rf-step-title">Je gegevens</legend>

                @if($user)
                    <p class="rf-signed-in">Je bestelt als <strong>{{ $user->email }}</strong>.</p>
                @else
                    <div class="rf-field">
                        <label for="email" class="rf-label">E-mailadres</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" maxlength="255" class="rf-input @error('email') rf-input-error @enderror" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" required aria-describedby="email-help">
                        <p class="rf-help" id="email-help">Hier sturen we je ticket of je toegang naartoe.</p>
                    </div>
                @endif

                @if($showUiTPAS)
                    <details class="rf-more" @if(old('uitpas')) open @endif>
                        <summary>Ik heb een UiTPAS met kansentarief</summary>
                        <div class="rf-field">
                            <label for="uitpas" class="rf-label">UiTPAS kaartnummer</label>
                            <input type="text" name="uitpas" id="uitpas" value="{{ old('uitpas') }}" maxlength="64" class="rf-input" inputmode="numeric" autocomplete="off" aria-describedby="uitpas-help">
                            <p class="rf-help" id="uitpas-help">
                                Wij passen dan het juiste tarief toe.
                                @if(organisation()->getContactOptionsText())
                                    Ondervind je problemen? {!! organisation()->getContactOptionsText() !!}
                                @endif
                            </p>
                        </div>
                    </details>
                @endif
            </fieldset>

            @if($recaptchaSiteKey)
                <div class="rf-captcha">
                    <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                </div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @endif

            <button type="submit" class="rf-button rf-submit">Betalen</button>

            <div class="rf-fineprint">
                <p>
                    De betaling verloopt via de beveiligde betaalpagina van
                    <a href="{{ config('app.owner.url') }}" target="_blank">{{ config('app.owner.name') }}</a>.
                    Onbetaalde bestellingen vervallen na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten.
                    Zie ook ons <a href="{{ action('DocumentController@privacy') }}">privacybeleid</a> en de
                    <a href="{{ action('DocumentController@tos') }}">gebruiksvoorwaarden</a>.
                </p>
                @if($event->venue)
                    <p>
                        Bij dit evenement worden foto- en video-opnames gemaakt. Wanneer je deelneemt, geef je de organisatie
                        toestemming om beelden waar je op staat te gebruiken voor communicatiedoeleinden.
                    </p>
                @endif
            </div>
        </form>

        @if(!$user)
            <p class="rf-login">Heb je al een account? <a href="{{ $loginUrl }}">Log in</a>.</p>
        @endif

    @endif

</div>

@endsection
