{{-- The Charon form of one of the automatic mails. --}}
@extends('charonfrontend::crud.form')

@section('cfcontent')

    @php($mail = $resource ? $resource->toArray() : [])

    <p>{{ $mail['description'] ?? '' }}</p>

    <p class="alert alert-info">
        @if(!empty($mail['customised']))
            Je organisatie gebruikt een aangepaste versie van deze mail.
        @else
            Je vertrekt van de standaardversie. Sla je niets op, dan blijft de standaardversie verstuurd worden.
        @endif
    </p>

    @parent

@endsection
