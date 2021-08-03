@extends('layouts/register')

@section('title')
    {{ $event->name }}
@endsection

@section('register-content')

    <h2 class="intro-title">{{ $event->name }}</h2>
    <h3 class="intro-sub-title">{{ $event->getOrderLabel() }}</h3>

    <h3>Tickets</h3>
    <p>
        Kies het type ticket dat u wilt bestellen.
    </p>

    <h3>Tickets</h3>
    @include('blocks.tickets-table')

@endsection
