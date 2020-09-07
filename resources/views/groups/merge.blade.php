@extends('layouts/front')

@section('title')
    {{ $group->name }} Samenvoegen
@endsection

@section('content')

    <h2>{{ $group->name }} samenvoegen</h2>
    <p>
        Door twee teams samen te voegen worden de scores van beide groepen gedeeld. Dat kan nodig zijn als je
        bijvoorbeeld per ongeluk twee groepen hebt aangemaakt. Om van deze functie gebruik te maken moet
        je administrator van beide teams zijn. Ben je dat niet? Vraag dan aan een administrator van de andere
        groep om jou eerst uit te nodigen.
    </p>

    <?php
    $properties = [
        'class' => 'form-control'
    ];
    ?>

    <h3>Samenvoegen</h3>
    @if(count($otherGroups) === 0)

        <p>Je behoort niet tot andere teams, dus kan je geen teams samenvoegen.</p>

    @else

        {{ Form::open([ 'action' => [ 'GroupController@mergeGroup', $group->id ] ]) }}
        <p>Kies het team waarmee je {{ $group->name }} wilt samenvoegen.</p>

        <div class="form-group">
            {{ Form::label('id', $group->name . ' samenvoegen met') }}
            {{ Form::select('id', $otherGroups, null, $properties) }}
        </div>

        {{ Form::submit('Samenvoegen', array('class' => 'btn btn-primary')) }}

        {{ Form::close() }}

    @endif

@endsection