@extends('layouts/front')

@section('title')
    {{ $person->name }}
@endsection

@section('content')

    <h2>{{ $person->name }}</h2>
    @if($person)
        {!! $person->description !!}
    @endif

    <h3>Evenementen</h3>
    <ul>
    @foreach ($person->getPersonEvents() as $personEvent)
        <?php $event = $personEvent->getEvent(); ?>
        <li>
            <a href="{{ $event->getUrl() }}">{{ $event->name }}</a>
            <small>(@foreach($personEvent->getRoles() as $role){{ $loop->first ? '' : ', ' }}{{ __('roles.' . $role) }}@endforeach)</small>
        </li>

    @endforeach
    </ul>

@endsection
