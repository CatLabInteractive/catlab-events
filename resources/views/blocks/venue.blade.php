<section class="kv-sec" id="locatie">
    <div class="kv-wrap">
        <h2 class="kv-sec-title">Locatie <small>{{ $venue->name }}, {{ $venue->city }}</small></h2>

        <div class="kv-venue">
            <div class="kv-text">
                {!! $venue->description !!}

                <address class="kv-address">
                    {{ $venue->name }}<br />
                    {{ $venue->address }}<br />
                    {{ $venue->postalCode }} {{ $venue->city }}<br />
                    {{ $venue->country }}
                </address>
            </div>

            @if($venue->lat && $venue->long)
                <div
                    id="map"
                    class="kv-map"
                    data-coordinates="{{ json_encode([ 'lat' => floatval($venue->lat), 'lng' => floatval($venue->long) ]) }}"
                    data-address="{{ $venue->getAddressFull("<br>") }}"
                ></div>
            @endif
        </div>
    </div>
</section>
