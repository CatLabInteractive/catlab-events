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

        @if (count($events) === 0)
            <p class="kv-text">Er zijn nog geen evenementen gepland.</p>
        @else
            <div class="kv-cards">
                @foreach($events as $v)
                    @include('blocks.event-card', [ 'cardEvent' => $v ])
                @endforeach
            </div>
        @endif
    </div>
</section>

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
