@if($order->isAccepted())
    @if($livestreamUrl = $order->event->getIdentifiedLiveStreamUrl($order->group))
        <div class="alert alert-success">
            <p><strong>Jij bent er (digitaal) bij!</strong></p>

            <p>
                We hebben je een mailtje met wat uitleg verstuurd, daarin staat ook de link om te spelen.<br />
                Je kan het spel echter ook al opstarten met onderstaande knop:
            </p>

            <a href="{{ $livestreamUrl }}" class="btn btn-success">Start spel</a>
        </div>
    @endif
@endif
