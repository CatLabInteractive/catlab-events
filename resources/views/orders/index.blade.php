@extends('layouts/front')

@section('title')Mijn tickets @endsection

@section('content')

    <section class="kv-sec">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">Overzicht <small>Tickets zijn pas geldig zodra ze online betaald zijn.</small></h2>

            @if (count($orders) === 0)
                <div class="kv-text">
                    <p>Je hebt nog geen tickets.</p>
                    <p class="kv-links"><a href="{{ action('EventController@calendar') }}">Bekijk de kalender</a></p>
                </div>
            @else
                <ul class="kv-orders">
                    @foreach($orders as $order)
                        <?php
                            $orderEvent = $order->event;
                            $orderMeta = [];
                            if ($order->ticketCategory && $order->ticketCategory->eventDates->count() > 0) {
                                $orderMeta[] = $order->ticketCategory->getDatesForDisplay();
                            } elseif ($orderEvent && $orderEvent->isPackage()) {
                                $orderMeta[] = 'Quizpakket';
                            }
                            if ($order->group) {
                                $orderMeta[] = 'Team ' . $order->group->name;
                            }
                            if ($orderEvent && $orderEvent->venue) {
                                $orderMeta[] = $orderEvent->venue->name;
                            }
                        ?>
                        <li class="kv-order {{ $orderEvent ? $orderEvent->getColourClass() : '' }} @if($order->isPending()) is-pending @endif">
                            <i class="kv-order-dot" aria-hidden="true"></i>

                            <div class="kv-order-main">
                                @if($orderEvent)
                                    <a class="kv-order-name" href="{{ $orderEvent->getUrl() }}">{{ $orderEvent->name }}</a>
                                @endif
                                @if(count($orderMeta) > 0)
                                    <span class="kv-order-meta">{{ implode(' · ', $orderMeta) }}</span>
                                @endif
                                @if($order->isPending())
                                    <span class="kv-order-flag">Nog niet betaald</span>
                                @endif
                            </div>

                            <div class="kv-order-action">
                                @if($order->isPending())
                                    <a href="{{ $order->getPayUrl() }}" class="kv-btn kv-btn-small">
                                        Betaal nu
                                        <span class="kv-btn-dot" aria-hidden="true">&rarr;</span>
                                    </a>
                                @elseif ($order->isAccepted())
                                    <a href="{{ action('OrderController@view', $order->id ) }}" class="kv-order-link">Bekijken &rarr;</a>
                                @else
                                    <span class="kv-order-state">{{ $order->state }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

@endsection
