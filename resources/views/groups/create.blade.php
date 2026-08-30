@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8">
    @csrf
    {{ method_field($method) }}

    <h3>Team</h3>

    @if(isset($event))
        @include('events/teamsizeWarning', [ 'event' => $event ])
    @endif

    @if($verb === 'post')
        <p class="alert alert-warning">
            Heeft je team al eens meegedaan aan een quiz van {{ organisation()->name }}? Maak dan geen nieuw team aan maar gebruik het bestaande team.
        </p>
    @endif

    <p>Kies een leuke en originele naam voor je team.</p>

    @include('charonfrontend::crud.form-fields')

    <div class="form-group row">
        <input type="submit" value="{{ ucfirst($verb) }}" class="btn btn-primary">
    </div>

    </form>


@endsection
