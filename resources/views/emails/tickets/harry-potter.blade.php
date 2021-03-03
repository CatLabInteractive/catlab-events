@extends('emails/layouts/layout')

@section('content')

    <h2>We zijn er bij!</h2>
    <p>
        Beste leden van {{ $group->name }},
    </p>

    <p>
        We zijn er bij!
    </p>

    @if($event->venue)
        <p>
            Op <strong>{{ $event->startDate->format('d/m/Y') }}</strong> gaan we naar
            <strong>{{ $event->venue->name }}</strong> om deel te nemen aan <strong>{{ $event->name }}</strong>.
        </p>
    @else
        <p>
            Op <strong>{{ $event->startDate->format('d/m/Y') }}</strong> spelen we <strong>{{ $event->name }}</strong>.
        </p>
    @endif

    @if($event->doorsDate)
        <p>
            Aanmelden kan vanaf <strong>{{ $event->doorsDate->format('H:i') }}</strong>, de quiz zelf start stipt om
            {{ $event->startDate->format('H:i') }}.
        </p>
    @else
        <p>
            De quiz start stipt om <strong>{{ $event->startDate->format('H:i') }}</strong>, meld je daarom zeker
            voor {{ $event->startDate->format('H:i') }} aan.
        </p>
    @endif

    <h3>Voorbereiding</h3>
    <ul>
        <li>
            Stel de leden van je team in op de <a
                    href="{{ action('GroupController@show', [ $group->id ]) }}">{{ $group->name }} team pagina</a>.
        </li>

        <li>
            Like onze <a href="https://www.facebook.com/quizfabriek/">facebook pagina</a> voor de laatste nieuwtjes.
        </li>

        <li>
            Zorg ervoor dat je tablet (1 per team) opgeladen is.
        </li>
    </ul>

    <h3>Verkleed jezelf: geen wisseldrank nodig!</h3>
    <p>Om de magische sfeer helemaal naar jouw huiskamer te brengen, kunnen jij en je teamgenoten jezelf verkleden in
        het thema. Kleed zeker ook je woonkamer in en haal die boterbiertjes boven, maak er een heuse toveravond
        van!</p>

    <p>Wanneer jullie helemaal uitgedost zijn, kunnen jullie tijdens de quiz een foto nemen (waarop duidelijk te zien is
        dat jullie zo gekleed mee aan het quizzen zijn) en deze doorsturen naar selfie@quizfabriek.be. De leukste foto’s
        worden ter plekke, tijdens de quiz, getoond! Geen paniek wanneer jullie allemaal meespelen via een videocall,
        wees bijvoorbeeld creatief met jullie virtuele achtergrond.</p>

    <p>Je moet dat niet zomaar doen: het team dat het aller leukste en origineelste in het thema ingekleed is, krijgt
        een leuke prijs toegestuurd! Uiteraard wordt er ook voor de winnaars van de quiz een attentie voorzien.</p>

    <h3>Zweinstein of Hogwarts?</h3>
    <p>De presentatie van deze quiz zal volledig in het Nederlands verlopen, dus ook met Nederlandstalige termen. Je kan
        echter voor de start van de quiz de optie selecteren om op je tablet of smartphone de antwoordopties met
        Engelstalige termen te zien, indien jullie daar meer vertrouwd mee zijn. Zo maakt iedereen evenveel kans.</p>



    @if($event->venue)
        <p>De quiz gaat door in:</p>
        <p>
            <strong>{{ $event->venue->name }}</strong><br>
            {{ $event->venue->address }}<br>
            {{ $event->venue->city }}
        </p>

        <p>
            Meld je voor {{ $event->startDate->format('H:i') }} aan bij de inschrijvingstafel. Daar ontvang je de
            (geheime)
            team-code. Deze code is strikt persoonlijk; laat hem niet aan de andere teams zien. Daarna mag je zelf
            een tafeltje kiezen.
        </p>
    @elseif($event->getLiveStreamUrl())
        <p>
            We spelen de quiz online via {{ $event->getLiveStreamUrl() }}. Je krijgt nog een afzonderlijke mail met
            je persoonlijke code die je nodig hebt om deel te nemen.
        </p>
    @endif

    <p>
        Veel quizplezier!<br/>
        De Quizfabriek
    </p>

@endsection
