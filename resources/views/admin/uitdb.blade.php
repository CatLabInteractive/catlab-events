@extends('layouts/admin')

@section('title')
    UitDB Link
@endsection

@section('content')

    <h2>UitDB Authentication</h2>
    @if($user)
        <p class="alert alert-success">
            Organisatie <strong>{{ $organisation->name }}</strong> is verbonden met UitID <strong>{{ $user->nick }}</strong>.
            <a href="{{ action('Admin\\UitDbController@unlink') }}">Link verwijderen</a>
        </p>

    @else
        <p class="alert alert-info">
            Om de UitPAS functionaliteiten te kunnen gebruiken, moet je een UitDB account verbinden.
            <a href="{{ action('Admin\\UitDbController@link') }}">Link uitdb account</a>
        </p>
    @endif

@endsection
