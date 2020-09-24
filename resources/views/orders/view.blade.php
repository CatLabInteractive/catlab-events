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

    <div class="invoice">
        <h3>Overzicht</h3>
        <table class="table">
            <tr>
                <td>Team</td>
                <td>{{ $order->group->name }}</td>
            </tr>

            <tr>
                <td>Evenement</td>
                <td>{{ $order->event->name }}</td>
            </tr>

            <tr>
                <td>Datum</td>
                <td>{{ $order->event->startDate->format('d/m/Y H:i') }}</td>
            </tr>

            <tr>
                <td>Locatie</td>
                <td>{{ $order->event->venue->getAddressFull() }}</td>
            </tr>

            <tr>
                <td>Categorie</td>
                <td>{{ $order->ticketCategory->name }}</td>
            </tr>

            @foreach($orderData['items'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>
                        {{ toMoney($item['price']) }}
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
