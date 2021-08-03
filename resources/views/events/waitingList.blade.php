@extends('layouts/front')

@section('title')
    {{ $event->name }}
@endsection

@section('content')

    <h2>{{ $event->name }}</h2>

    @if(!$event->isSoldOut())
        <div class="alert alert-success">
            <p>
                Er zijn nog tickets voor <strong>{{ $event->name }}</strong>.
                Je kan nog gewoon <a href="{{ action('EventController@selectTicketCategory', [ $event->id ] ) }}"><i class="fa fa-ticket"></i> {{ $nextEvent->getOrderLabel() }}</a>.
            </p>
        </div>

    @else

        <p>Jammer, je bent te laat om in te schrijven voor {{ $event->name }}.</p>

        <h3>Wachtlijst</h3>
        <p>
            Soms komen er tickets vrij, door annulaties of extra capaciteit.<br />
            In dat geval geven we eerst mensen van de wachtlijst de kans om een ticket te kopen.
        </p>

        <p>
            Een plaatsje in de wachtlijst geeft uiteraard geen garantie op een ticket.
        </p>

        @if($goingGroup)

            <div class="alert alert-warning">
                <p>
                    Je hebt al tickets voor team "{{ $goingGroup->name }}".
                </p>
            </div>

        @elseif($waitingListItem)

            <div class="alert alert-success">
                <p>
                    Je staat op de wachtlijst sinds {{ $waitingListItem->pivot->created_at->formatLocalized('%A %d %B %Y, %H:%M') }}.<br />
                    Indien er tickets vrij komen sturen we je een mailtje.
                </p>
            </div>

        @else

            <form method="get" action="{{ action('WaitingListController@addToWaitinglist', [ $event->id ]) }}">

                <button class="btn btn-success">Inschrijven op wachtlijst</button>

            </form>

        @endif

    @endif

    @if($adminLink)
        <a href="{{$adminLink}}" class="btn btn-default">Lijst bekijken</a>
    @endif

@endsection
