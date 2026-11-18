@extends('layouts/front')

@section('title')
    {{ $pageTitle ?? 'Quiz kalender' }}
@endsection

@section('bodyClass')kv-page @endsection

@section('content')

    @include('blocks.agenda')

@endsection