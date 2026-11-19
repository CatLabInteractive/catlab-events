@extends('layouts/front')

@section('title')
    {{ $venue->name }}
@endsection

@section('bodyClass')kv-page @endsection

@section('content')

    @include('blocks.venue')

    @include('blocks.agenda')

@endsection
