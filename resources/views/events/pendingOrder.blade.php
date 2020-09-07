@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

    <?php
    $properties = [
        'class' => 'form-control'
    ];
    ?>

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">Registreren</h3>

    <div class="alert alert-danger">

        <p>
            Je hebt nog een onbetaalde registratie van <strong>{{ $pendingOrder->group->name }}</strong> voor <strong>{{ $pendingOrder->event->name }}</strong>.<br />
            Om een nieuwe bestelling te plaatsen moet je deze eerst betalen of annuleren.
        </p>

        <p>
            <a href="{{ $pendingOrder->getPayUrl() }}" class="btn btn-success">Betaal nu</a>
            <a href="{{ $cancelUrl  }}" class="btn btn-danger">Annuleren</a>
        </p>

    </div>


@endsection
