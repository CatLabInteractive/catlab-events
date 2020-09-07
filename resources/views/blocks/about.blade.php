<section id="about">
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <h2>Wat is de Quizfabriek?</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-6">
                <h3>Entertainment</h3>
                <p>
                    De quizfabriek organiseert elk jaar een aantal <a href="http://www.quizwitz.com/">QuizWitz</a>
                    quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn
                    dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door
                    een ronde armworstelen, retoriek of zelfs bier-proeven. Verwacht je een klassieke pen-en-papier quiz?
                    Sla deze dan liever over.
                </p>

                <h3>Smart devices</h3>
                <p>
                    Antwoorden op de quizzes van <a href="http://www.quizwitz.com/">QuizWitz</a> gebeurt met een tablet
                    of een smartphone. Breng daarom steeds minstens één opgeladen smart device (tablet, iPad of
                    smart phone) <strong>per team</strong> mee. Wij voorzien een WiFi-netwerk waarop je kan verbinden
                    om mee te spelen. Laptops zijn niet toegestaan.
                </p>

                <p>
                    Bij aankomst krijg je de code die je nodig hebt
                    om jouw smart device te verbinden met het spel.
                </p>

                <h3>Puntentelling</h3>
                <p>
                    Per vraag kunner er 1000 punten verdiend worden. Hoe sneller je antwoordt, hoe meer punten je verdient.
                    In multiple-choice vragen verlies je per milliseconden punten, terwijl bij open vragen
                    in schalen van 200 punten wordt gewerkt.
                </p>
            </div>

            <div class="col-sm-6">
                <h3>Seizoen</h3>
                <p>
                    Het quizseizoen loopt van september tot mei en bestaat dit jaar uit 4 quizzes.
                </p>

                <h3>Prijzen</h3>
                <p>
                    Amuseren is het belangrijkste, maar als je goed presteert maak je ook kans op toffe prijzen.
                    De grootste prijs is natuurlijk voorbehouden voor de Seizoenswinnaar...
                </p>

                <h3>Seizoenswinnaar</h3>
                <p>
                    Teams die meermaals deelnemen aan onze quizzes maken kans om seizoenswinnaar te worden en kunnen
                    daarmee een leuke prijs winnen. Na elke quiz wordt een nieuwe rangschikking gepubliceerd.
                </p>

                <h3>Rondes</h3>
                <p>
                    De quiz bestaat uit 7 fascinerende kennisrondes van elk 10 vragen met telkens ertussen een
                    knotsgek waaghalzenduel. Het spel duurt ongeveer drie uur, inclusief pauze.
                </p>

                <!--
                <p>Volgende rondes komen aan bod:</p>
                <ul>
                    <li>Natuur & geografie</li>
                    <li>Amusement</li>
                    <li>Wetenschap & technologie</li>
                    <li>Geschiedenis</li>
                    <li>Sport & vrije tijd</li>
                    <li>Kunst & cultuur</li>
                    <li>Actualiteit / Specifiek thema</li>
                </ul>
                -->

                <h3>Waaghalzenduels</h3>
                <p>
                    Tussen twee rondes is er een waaghalzenduel waarbij twee of meer teams het tegen elkaar opnemen
                    in een ludieke strijd. De winnaar van het duel verdient 1000 punten voor zijn team.
                    Vaak kan eenduidig een winnaar aangeduid worden, maar voor enkele duels worden subjectieve
                    criteria gebruikt.
                </p>


            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                @if($nextEvent)
                    <a href="{{ $nextEvent->getUrl() }}" class="btn btn-primary">Inschrijven</a>
                @endif
                <a href="mailto:hallo@quizfabriek.be" class="btn btn-primary">Vragen? Contacteer ons</a>
                <a href="https://www.facebook.com/quizfabriek" class="btn btn-primary" target="_blank"><i class="fa fa-facebook"></i></a>
            </div>
        </div>
    </div>
</section>