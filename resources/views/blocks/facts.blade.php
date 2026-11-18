@if(organisation()->events()->count() > 0)
    <?php
        $firstQuizDate = organisation()->getFirstEventDate();
        $yearsActive = $firstQuizDate ? (new DateTime())->diff($firstQuizDate)->y : 0;
        $facts = [];
        if ($yearsActive > 0) {
            $facts[] = [ $yearsActive, 'jaar actief' ];
        }
        $facts[] = [ organisation()->countEvents(), 'quizzen' ];
        $facts[] = [ organisation()->countGroups(), 'teams' ];
        $facts[] = [ organisation()->countPlayers(), 'spelers' ];
    ?>
    <section class="kv-sec kv-band" id="facts">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">Dit is {{ organisation()->name }}</h2>
            <dl class="kv-facts-grid">
                @foreach($facts as $fact)
                    <div>
                        <dt>{{ $fact[1] }}</dt>
                        <dd>{{ number_format($fact[0], 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endif
