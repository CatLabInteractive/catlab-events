<div class="help">

    @if($code)
        <h2>Welkom!</h2>
        <p>
            Dit is de livestream, hier zal de quizmaster straks presenteren.
            Als alles goed gaat hoor je nu muziek en kan je genieten van ons wachtscherm.
            Hoor je niets? Probeer dan even de stappen hieronder of neem contact met ons op.
        </p>

        <p>
            De vragen worden hier gesteld, maar je antwoorden ingeven doe je best op een smartphone
            of tablet.
        </p>

        <ol class="list">
            <li>
                Surf op je smartphone naar <code>www.quizwitz.tv</code>
            </li>

            <li>
                 Geef jouw persoonlijke code in: <code>{{ $code }}</code>
            </li>

            <li>
                Je bent klaar om te spelen!
            </li>
        </ol>
    @endif

    <h3>Geen audio of video?</h3>
    <h4>Help, ik heb geen beeld!</h4>
    <ul class="list">
        <li>
            Zie je hierboven geen video of staat het beeld stil of staat er dat de stream offline is?
            Probeer dan eerst de <a href="{{ url()->current() }}">pagina te herladen</a>.
        </li>

        <li>
            Om onze livestream te volgen heb je een moderne webbrowser nodig. Wij verkiezen Google Chrome of Firefox.
        </li>

        <li>
            Sommige bedrijfsnetwerken blokkeren de livestream dienst die we gebruiken. Daar kunnen we helaas niets
            aan doen. Verbreek de verbinding met je VPN netwerk of gebruik een ander toestel.
        </li>

        <li>
            Blijft de video vaak hangen? Probeer naar een plek te gaan waar je betere WiFi ontvangst hebt, of gebruik
            je mobiele netwerk.
        </li>
    </ul>

    <h4>Help, ik heb geen geluid!</h4>
    <ul class="list">
        <li>Kijk of het geluid van de video aan staat. Links onderaan in de video player kan je het volume aanpassen.</li>
        <li>Kijk na of het geluid van je toestel aan staat. Probeer bijvoorbeeld even of je op andere websites wel geluid hebt.</li>
    </ul>

    <h4>Help, ik kan niet antwoorden!</h4>
    <ul class="list">
        <li>
            Antwoorden doorsturen gebeurt op een tweede toestel en dus niet op deze pagina. Volg de instructies
            in de email om je antwoordscherm te verbinden.
        </li>

        <li>
            Verschijnen de vragen te vroeg op je antwoord toestel? Dan loopt de livestream voor jou vast achter.
            Klik even op 'pauze' en 'starten' of <a href="{{ url()->current() }}">herlaad deze pagina</a> om terug helemaal mee te zijn.
            Gebruik liever geen Chromecast of Apple TV want dat kan extra vertragingen veroorzaken.
        </li>

        <li>
            Krijg je vaak 'connection lost' op het antwoordscherm? Mogelijk komt het door dat je toestel uit standby
            komt. Houdt je scherm opgelicht door tussen de vragen in af en toe te tikken, of schakel de screensaver
            event uit. Blijf je proberen ondervinden? Mogelijk heb je geen stabiele wifi verbinding, schakel over op
            je mobiele netwerk.
        </li>

        <li>
            Blijft 'Connection lost' staan of kan je niet antwoorden? Ververs dan even de pagina op je toestel.
        </li>
    </ul>

    <p>
        Onze oplossing voor alles? <a href="{{ url()->current() }}">Herlaad de pagina</a>!
    </p>
</div>
