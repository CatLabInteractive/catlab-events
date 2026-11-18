@extends('charonfrontend::layouts.crud')

@section('title')
    Mijn teams
@endsection

@section('cfcontent')

    {{ $table->render() }}

@endsection