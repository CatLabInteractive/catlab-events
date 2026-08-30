@extends('layouts/livestream')

@section('title')
    {{$livestream->title}}
@endsection

@section('content')

    <h1>{{ $livestream->title }}</h1>

    <form method="POST" action="{{ url()->current() }}" accept-charset="UTF-8">
        @csrf

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

        <label for="uitpasNumber" class="form-input">UiTPAS Nummer</label><br />
        <input type="text" name="uitpasNumber" id="uitpasNumber" value="{{ old('uitpasNumber') }}" class="form-input"><br />
        <input type="submit" value="Verzenden">

    </form>

    @endif

    @include('livestream.footer', [ 'hideSupport' => true ])

@endsection

@section('script')
@endsection
