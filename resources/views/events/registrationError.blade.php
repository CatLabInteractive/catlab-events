@extends('layouts/register')

@section('title')
    Registreren voor {{ $event->name }}
@endsection

@section('register-content')

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">{{ $event->getOrderLabel() }}</h3>

    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-danger">
                {{ $error }}
            </div>
        </div>
    </div>

@endsection
