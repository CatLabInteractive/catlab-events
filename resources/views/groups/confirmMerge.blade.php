@extends('layouts/front')

@section('title')
    {{ $group->name }} Samenvoegen
@endsection

@section('content')

    <h2>{{ $group->name }} samenvoegen met {{ $otherGroup->name }}</h2>
    <p>Ben je zeker dat de teams <strong>{{ $group->name }}</strong> en <strong>{{ $otherGroup->name }}</strong> wilt samenvoegen?</p>
    <p>De teamleden en behaalde scores van beide teams zullen gecombineerd worden in een nieuw team.</p>

    <p>De naam van de gecombineerde groep zal <strong>{{ $group->name }}</strong> zijn.</p>

    <p><strong>Deze actie kan niet ongedaan gemaakt worden.</strong></p>

    {{ Form::open([ 'action' => [ 'GroupController@processMergeGroup', $group->id ] ]) }}

    {{ Form::hidden('id', $otherGroup->id) }}

    <a href="{{ action('GroupController@show', $group->id) }}" class="btn btn-default">Toch maar niet</a>
    {{ Form::submit('Samenvoegen', array('class' => 'btn btn-primary')) }}

    {{ Form::close() }}

@endsection