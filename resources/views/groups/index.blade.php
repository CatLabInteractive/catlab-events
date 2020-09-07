@extends('charonfrontend::layouts.crud')

@section('title')
    Mijn teams
@endsection

@section('cfcontent')

    <h2>Mijn teams</h2>
    {{ $table->render() }}

@endsection