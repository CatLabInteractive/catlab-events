@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

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
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
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

        <form method="POST" action="{{ $action }}" accept-charset="UTF-8">
            @csrf

            <div class="form-group">

                <label for="group">Team</label>
                <select name="group" id="group" class="form-control">
                    @foreach($groups as $value => $label)
                        <option value="{{ $value }}" @selected((string)old('group') === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>

            </div>

            <input type="submit" value="Registreren" class="btn btn-primary">

        </form>

    @endif


@endsection
