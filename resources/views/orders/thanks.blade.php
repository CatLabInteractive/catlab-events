@extends('layouts/front')

@section('title')
    Bestelling bevestigd
@endsection

@section('content')

    <h2>{{ $order->event->name }}</h2>
    @if($order->isCancelled())
        <div class="alert alert-danger">
            <strong>Oh nee!</strong> De betaling is mislukt. <br />

            {{ Form::open(array('url' => $retryFormAction)) }}
            @foreach($retryFormInput as $k => $v)
                {{ Form::hidden($k, $v) }}
            @endforeach
            {{ Form::submit('Probeer het opnieuw', array('class' => 'btn btn-danger')) }}
            {{ Form::close() }}

        </div>
    @elseif($order->isPending())
        <div class="alert alert-warning">
            <strong>Joepie!</strong> We verwerken je betaling.
            Zodra die is goedgekeurd, krijg je jouw tickets via email.
        </div>
    @elseif($order->isAccepted())
        <div class="alert alert-success">
            <strong>Joepie!</strong> Je bent ingeschreven!
        </div>
    @endif

    @if($trackConversion)

        <script type="text/javascript">

            // load the page again to avoid double tracking.
            var redirect_url = '{{ $redirectUrl }}';
            // fallback
            setTimeout(function() {
                window.location = redirect_url;
            }, 2000);


            var trackerData = {!! json_encode([
                'event' => 'order.confirmed',
                'orderId' => $order->id,
                'orderEvent' => $order->ticketCategory->event->name,
                'orderEventId' => $order->ticketCategory->event->id,
                'ticketCategory' => $order->ticketCategory->name,
                'ticketCategoryId' => $order->ticketCategory->id,
                'orderPrice' => $order->ticketCategory->getTotalPrice()
            ]) !!};

            trackerData.eventCallback = function() {
                window.location = redirect_url;
            };

            dataLayer.push(trackerData);

        </script>
    @endif

    @if($order->event->getFacebookEventUrl())
        <div class="alert alert-info">
            <p>
                Mogen we je nog wat vragen? Zet je 'aanwezig' op ons <a href="{{ $order->event->getFacebookEventUrl() }}" class="btn btn-sm btn-info"><i class="fa fa-facebook"></i> facebook evenement</a>
                Dank je! <i class="fa fa-heart"></i>
            </p>
        </div>
    @endif

    <table class="table">

        <tr>
            <td>Evenement</td>
            <td>{{ $order->event->name }}</td>
        </tr>

        @if($order->group)
            <tr>
                <td>Team</td>
                <td>{{ $order->group->name }}</td>
            </tr>
        @endif

        @if($order->event->startDate)
            <tr>
                <td>Datum</td>
                <td>{{ $order->event->startDate->format('d/m/Y H:i') }}</td>
            </tr>
        @endif
    </table>

    @include('blocks.livestream-order-link', [ 'order' => $order ])

@endsection
