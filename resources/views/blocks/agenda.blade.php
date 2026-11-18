<?php
    // Dated quizzes and quiz packages (play whenever you want) get their own blocks.
    $agendaEvents = collect($events);
    $agendaPackages = $agendaEvents->filter(function ($e) { return $e->isPackage(); })->values();
    $agendaDated = $agendaEvents->reject(function ($e) { return $e->isPackage(); })->values();
?>
<section class="kv-sec">
    <div class="kv-wrap">
        <h2 class="kv-sec-title">
            Binnenkort
            @if(!empty($cities))
                <small>
                    Op zoek naar een leuke groepsactiviteit in {{ \App\Tools\StringHelper::joinList($cities) }}?
                    Bij ons vind je vast de leukste quiz in de buurt!
                </small>
            @endif
        </h2>

        @if ($agendaDated->count() === 0)
            <p class="kv-text">
                @if($agendaPackages->count() > 0)
                    Er zijn momenteel geen quizzen met een vaste datum gepland.
                @else
                    Er zijn nog geen evenementen gepland.
                @endif
            </p>
        @else
            <div class="kv-cards">
                @foreach($agendaDated as $v)
                    @include('blocks.event-card', [ 'cardEvent' => $v ])
                @endforeach
            </div>
        @endif
    </div>
</section>

@if($agendaPackages->count() > 0)
    <section class="kv-sec" id="quizpakketten">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">Quizpakketten <small>Speel wanneer je wil, thuis of op je eigen locatie.</small></h2>
            <div class="kv-cards">
                @foreach($agendaPackages as $v)
                    @include('blocks.event-card', [ 'cardEvent' => $v ])
                @endforeach
            </div>
        </div>
    </section>
@endif

@if(isset($pastEvents))
    <section class="kv-sec">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">Voorbije edities</h2>
            @if (count($pastEvents) === 0)
                <p class="kv-text">Er zijn geen voorbije edities.</p>
            @else
                @include('blocks.past-events', [ 'pastEvents' => $pastEvents ])
            @endif
        </div>
    </section>
@endif
