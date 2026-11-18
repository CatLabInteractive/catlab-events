@extends('layouts/front')

@section('title')
    {{ $venue->name }}
@endsection

@section('content')

    <h2>{{ $venue->name }}</h2>

    <h3>Locatie</h3>
    <table class="table">
        <tr>
            <td class="col-md-3">Naam</td>
            <td>{{ $venue->name }}</td>
        </tr>

        <tr>
            <td>Adres</td>
            <td>
                {{ $venue->address }}<br>
                {{ $venue->postalCode }} {{ $venue->city }}<br>
                {{ $venue->country }}
            </td>
        </tr>
    </table>

    {!! $venue->description !!}

    @include('blocks.agenda')

    @if($venue->lat && $venue->long)
        <section class="kv-sec" id="contact">
            <div class="kv-wrap">
                <div
                    id="map"
                    class="kv-map"
                    data-coordinates="{{ json_encode([ 'lat' => floatval($venue->lat), 'lng' => floatval($venue->long) ]) }}"
                    data-address="{{ $venue->getAddressFull("<br>") }}"
                    data-maps-key="{{ config('services.google.maps_api_key') }}"
                ></div>
            </div>
        </section>
    @endif

@endsection
