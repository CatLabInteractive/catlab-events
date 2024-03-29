@extends('layouts/register')

@section('title')
    {{ $order->event->name }}
@endsection

@section('register-content')

    <?php $event = $order->event; ?>

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">Mijn ticket</h3>

    @if($order->isPending())
    <div class="alert alert-warning">
        <p>
            <strong>Dit ticket is nog niet betaald en is dus nog niet geldig!</strong>
            <a href="{{ $order->getPayUrl() }}">Betaal nu</a>
        </p>
    </div>
    @endif

    @include('blocks.livestream-order-link', [ 'order' => $order ])

    <div class="invoice">
        <h3>Overzicht</h3>
        <table class="table">
            @if($order->group)
                <tr>
                    <td>Team</td>
                    <td>{{ $order->group->name }}</td>
                </tr>
            @endif

            <tr>
                <td>Evenement</td>
                <td>{{ $order->event->name }}</td>
            </tr>

            @if(count($order->ticketCategory->eventDates) > 0)
                <tr>
                    <td>Datum</td>
                    <td>{{ $order->ticketCategory->getDatesForDisplay() }}</td>
                </tr>
            @endif

            @if($order->event->venue)
                <tr>
                    <td>Locatie</td>
                    <td>{{ $order->event->venue->getAddressFull() }}</td>
                </tr>
            @endif

            <tr>
                <td>Categorie</td>
                <td>{{ $order->ticketCategory->name }}</td>
            </tr>

            @foreach($orderData['items'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>
                        {{ toMoney($item['price'] + $item['vat']) }}
                        <span class="small">(incl. {{ toMoney($item['vat']) }} btw)</span>
                    </td>
                </tr>
            @endforeach

            <tr class="total">
                <td>Totaal</td>
                <td>{{ toMoney($orderData['price']) }}</td>
            </tr>
        </table>
    </div>

@endsection
