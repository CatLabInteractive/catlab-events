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

    @include('blocks.agenda')

    <section id="contact">
        <script>
            var GEO_LOCATION = <?php echo json_encode($venue->getGeo()); ?>;
        </script>
        <div id="map">
            <div id="gmap-wrap">
                <div id="gmap">
                </div>
            </div>
        </div><!--/#map-->
    </section>

@endsection