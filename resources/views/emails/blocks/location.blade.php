{{-- Needs $event. --}}
@if($event->venue)
    <p>De quiz gaat door in:</p>
    <p>
        <strong>{{ $event->venue->name }}</strong><br>
        {{ $event->venue->address }}<br>
        {{ $event->venue->city }}
    </p>

    <p>
        Meld je aan bij de inschrijvingstafel. Daar ontvang je de (geheime)
        team-code. Deze code is strikt persoonlijk; laat hem niet aan de andere teams zien. Daarna mag je zelf
        een tafeltje kiezen.
    </p>
@elseif($event->getLiveStreamUrl())
    <p>
        We spelen de quiz online via {{ $event->getLiveStreamUrl() }}. Je krijgt nog een afzonderlijke mail met
        je persoonlijke code die je nodig hebt om deel te nemen.
    </p>
@endif
