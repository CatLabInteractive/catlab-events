{{-- The Charon index of the mails of one event: the "E-mails" tab of the event ($hub). --}}
@extends('charonfrontend::crud.index')

@section('cfcontent')

    @if($hub)
        @include('charonfrontend::crud.hub-header', [ 'hub' => $hub ])
    @else
        <h2>{{ $event->name }}</h2>
        <h3>E-mails</h3>
    @endif

    <p>
        Zonder aanpassing verstuurt dit evenement de standaardversie van de organisatie.
        Je kan per mail een sjabloon kiezen of een eigen tekst schrijven voor dit evenement.
    </p>

    @parent

@endsection
