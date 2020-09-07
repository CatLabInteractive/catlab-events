@extends('charonfrontend::layouts.crud')

@section('title')
    {{ $resource->getProperties()->getFromName('name')->getValue() }}
@endsection

@section('cfcontent')

    <h2>Details</h2>

    <table class="table">
        @foreach($resource->getProperties()->getResourceFields()->getValues() as $field)

            <tr>
                <th>{{ ucfirst($field->getField()->getDisplayName()) }}</th>
                <th>{{ $field->getValue() }}</th>
            </tr>

        @endforeach
    </table>

    @if(Auth::getUser()->can('merge', $resource->getSource()))
        <a class="btn btn-default" href="{{ action('GroupController@mergeGroup', [ $resource->getProperties()->getFromName('id')->getValue() ]) }}">
            Groepen samenvoegen
        </a>
    @endif

    @foreach($relationships as $relationship)

        <h2>{{ $relationship['title'] }}</h2>
        {{ $relationship['table']->render() }}

    @endforeach

@endsection

