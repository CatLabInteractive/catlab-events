@extends('layouts/front')

@section('title')
    Mijn tickets
@endsection

@section('content')

    <h2>Mijn tickets</h2>
    @if (count($orders) === 0)
        <p>Je hebt nog geen tickets.</p>
    @else

        <p>Tickets zijn pas geldig zodra ze online betaald zijn.</p>

        <table class="table">
            @foreach($orders as $order)

                <tr>
                    <td>
                        @if($order->event && $order->event->startDate)
                            {{ $order->event->startDate->format('d/m/Y H:i') }}
                        @endif
                    </td>

                    <td>
                        @if($order->event)
                            <a href="{{ $order->event->getUrl() }}">
                                {{ $order->event->name }}
                            </a>
                        @endif
                    </td>

                    <td>
                        @if($order->group)
                            {{ $order->group->name }}
                        @endif
                    </td>

                    <td>
                        @if($order->event && $order->event->venue)
                            <a href="{{ action('EventController@fromVenue', $order->event->venue->id) }}">
                                {{ $order->event->venue->name }}
                            </a>
                        @endif
                    </td>

                    <td>
                        @if($order->isPending())
                            <a href="{{ $order->getPayUrl() }}" class="btn btn-primary">Betaal nu</a>
                        @elseif ($order->isAccepted())
                            <a href="{{ action('OrderController@view', $order->id ) }}" class="btn btn-success">Bekijken</a>
                        @else
                            {{ $order->state }}
                        @endif
                    </td>
                </tr>

            @endforeach
        </table>
    @endif

@endsection
