@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

    <?php
    $properties = [
        'class' => 'form-control'
    ];
    ?>

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">{{ $event->getOrderLabel() }}</h3>

    @include('events/teamsizeWarning', [ 'event' => $event ])

    @if(count($groups) === 0)

        <p>
            Joepie! Je bent bijna klaar om te registreren voor {{ $event->name }}. <br />
            Maak eerst een nieuw team aan, daarna kan je registreren.
        </p>

        <a href="{{ $groupAddUrl }}" class="btn btn-primary">Nieuw team maken</a>
    @else

        @if(!$errors->isEmpty())
            <div class="alert alert-warning">
                {{ Html::ul($errors->all()) }}
            </div>
        @elseif(organisation()->getContactOptionsText())
            <div class="alert alert-warning">
                <p>
                    Heb je vragen of heb je hulp nodig?
                    {!! organisation()->getContactOptionsText() !!}
                </p>
            </div>
        @endif

        <p>
            Selecteer welk team je wilt registeren.<br />
            Je kan ook steeds een <a href="{{ $groupAddUrl }}">nieuw team aanmaken</a>.
        </p>

        {{ Form::open(array('url' => $action)) }}

        <div class="form-group">

            {{ Form::label('group', 'Team') }}
            {{ Form::select('group', $groups, old('group'), $properties) }}

        </div>

        {{ Form::submit('Registreren', array('class' => 'btn btn-primary')) }}

        {{ Form::close() }}

    @endif


@endsection
