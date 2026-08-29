@extends('layouts/admin')

@section('content')

    <h2>Terugbetaling</h2>

    @if(!$refundable)

        <p class="alert alert-info">
            Deze order kan hier niet terugbetaald worden.
            @if(!empty($unavailableReason))
                {{ $unavailableReason }}
            @elseif($order->state !== \App\Models\Order::STATE_ACCEPTED)
                De order staat op <code>{{ $order->state }}</code>.
            @elseif(!$order->catlab_order_id)
                Er is geen betaling aan gekoppeld (gratis ticket).
            @else
                Ze dateert van voor de terugbetaalknop en moet in het
                accounts admin panel terugbetaald worden.
            @endif
        </p>

        <p>
            <a class="btn btn-secondary" href="{{ action('Admin\OrderController@index') }}">Terug</a>
        </p>

    @else

    <div class="alert alert-danger">
        <strong>Dit is definitief.</strong>
        Het geld gaat terug naar de koper en de transactiekosten krijgen we
        niet terug. Dit kan niet ongedaan gemaakt worden.
    </div>

    <table class="table" style="max-width: 600px;">
        <tr>
            <th style="width: 160px;">Koper</th>
            <td>{{ $order->user ? $order->user->email : '?' }}
                @if($order->group) ({{ $order->group->name }}) @endif
            </td>
        </tr>
        <tr>
            <th>Event</th>
            <td>{{ $order->event->name }}</td>
        </tr>
        <tr>
            <th>Referentie</th>
            <td><code>{{ $reference }}</code></td>
        </tr>
        <tr>
            <th>Bedrag</th>
            <td>&euro; {{ number_format($amount, 2, ',', '.') }}</td>
        </tr>
    </table>

    <form action="{{ action('Admin\RefundController@processRefund', [ $order->id ]) }}" method="post" id="refund-form">
        {{ csrf_field() }}

        <div class="form-group" style="max-width: 400px;">
            <label for="reference">Typ <code>{{ $reference }}</code> om te bevestigen</label>
            <input type="text" class="form-control" id="reference" name="reference" autocomplete="off" />
        </div>

        <div class="form-group" style="max-width: 400px;">
            <label for="reason">Reden</label>
            <input type="text" class="form-control" id="reason" name="reason" maxlength="255" />
        </div>

        <p>
            <button type="submit" class="btn btn-danger" id="refund-submit" disabled>Terugbetalen</button>
            <a class="btn btn-secondary" href="{{ action('Admin\OrderController@index') }}">Annuleer</a>
        </p>
    </form>

    <script>
        (function () {
            var expected = {!! \Illuminate\Support\Js::from($reference) !!};
            var input = document.getElementById('reference');
            var button = document.getElementById('refund-submit');
            var form = document.getElementById('refund-form');

            input.addEventListener('input', function () {
                button.disabled = input.value.trim() !== expected;
            });

            // A double-click on a slow request would otherwise fire two
            // POSTs, both passing the server's isRefundable() re-check. This
            // is a convenience only -- the authoritative guard stays
            // server-side.
            form.addEventListener('submit', function () {
                button.disabled = true;
            });
        })();
    </script>

    @endif

@endsection
