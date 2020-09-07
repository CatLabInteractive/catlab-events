<section>
    <div class="container">

        <div class="row">

            @if($venue->lat && $venue->long)
                <div class="col-md-6">

                    <div
                            id="map"
                            style="height: 400px"
                            data-coordinates="{{ json_encode([ 'lat' => floatval($venue->lat), 'lng' => floatval($venue->long) ]) }}"
                            data-address="{{ $venue->getAddressFull("<br>") }}"
                    ></div>

                </div>
            @endif

            <div class="col-md-6">

                <h2 class="intro-title">Locatie</h2>
                <h3 class="intro-sub-title">{{ $venue->name }}</h3>

                {!! $venue->description !!}

                <address>
                    {{ $venue->name }}<br />
                    {{ $venue->address }}<br />
                    {{ $venue->postalCode }} {{ $venue->city }}<br />
                    {{ $event->venue->country }}
                </address>
            </div>



        </div>


    </div>
    <!-- Container end -->
</section>