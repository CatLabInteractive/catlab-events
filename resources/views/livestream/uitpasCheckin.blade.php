@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    <h1>{{ $livestream->title }}</h1>

    {{ Form::open() }}

    @if($success)
        <p style="color: green;">{{ $success }}</p>
    @endif

    @if($error)
        <p style="color: red;">{{ $error }}</p>
    @endif

    @if(!$success)

        <p>
            Super leuk dat je er bij bent!
        </p>

        <p>
            Heb je een UiTPAS?<br />
            Geef je code in om punten te sparen.
        </p>

        {{ Form::label('uitpasNumber', 'UiTPAS Nummer', [ 'class' => 'form-input' ]) }}<br />
        {{ Form::text('uitpasNumber', old('uitpasNumber'), [ 'class' => 'form-input' ]) }}<br />
        {{ Form::submit('Verzenden') }}

        {{ Form::close() }}

    @endif

    @include('livestream.footer', [ 'hideSupport' => true ])

@endsection

@section('script')
@endsection
