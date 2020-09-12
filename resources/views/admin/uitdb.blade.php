@extends('layouts/admin')

@section('title')
    UitDB Link
@endsection

@section('content')

    <h2>UitDB Authentication</h2>

    <p><a href="{{ action('Admin\\UitDbController@link') }}">Link uitdb account</a></p>

@endsection
