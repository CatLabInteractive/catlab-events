@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

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
        {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}
    </div>

    {{ Form::close() }}

@endsection
