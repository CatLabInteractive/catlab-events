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

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    {{ Form::open(array('url' => $action)) }}
    @foreach($input as $k => $v)
        {{ Form::hidden($k, $v) }}
    @endforeach

    <p>Leuk dat jullie erbij willen zijn, {{ $group->name }}!</p>
    <!--
    <div class="alert alert-warning">
        <p>
            <strong>Je registratie wordt pas bevestigd na online betaling.</strong><br />
            Onbetaalde tickets worden na {{ \App\Models\Event::ORDER_TIMEOUT_MINUTES }} minuten terug vrijgegeven.
        </p>

        <p>De betaling verloopt via de beveiligde betaalterminal van <a href="{{ config('app.owner.url') }}" target="_blank">{{ config('app.owner.name') }}</a>.</p>

        <p>
            {{ Form::submit('Naar de betaalterminal', array('class' => 'btn')) }}
        </p>
    </div>
    -->

    @if($event->venue)
        <div class="alert alert-info">
            <p>
                Bij dit evenement worden foto en video opnames gemaakt. Wanneer je aan dit evenement deelneemt,
                geef je de organisatie toestemming om beelden (foto en video) waar je op staat te gebruiken
                voor communicatiedoeleinden.
            </p>
        </div>
    @endif

    <div class="invoice">
        <h3>Overzicht</h3>
        <table class="table">

            <tr>
                <td>Team</td>
                <td>{{ $group->name }}</td>
            </tr>

            <tr>
                <td>Evenement</td>
                <td>{{ $event->name }}</td>
            </tr>

            <tr>
                <td>Datum</td>
                <td>{{ $event->startDate->format('d/m/Y H:i') }}</td>
            </tr>

            @if($event->venue)
            <tr>
                <td>Locatie</td>
                <td>{{ $event->venue->getAddressFull() }}</td>
            </tr>
            @endif

            <tr>
                <td>Inschrijving</td>
                <td>{{ $ticketCategory->getFormattedPrice() }}</td>
            </tr>

            <tr>
                <td>Transactiekosten</td>
                <td>{{ $ticketCategory->getFormattedTransactionFee() }}</td>
            </tr>

            <tr class="total">
                <td>Totaal</td>
                <td>{{ $ticketCategory->getFormattedTotalPrice() }}</td>
            </tr>
        </table>

    </div>

    @if($event->organisation->uitpas)
        <h3>UitPAS</h3>
        <p>
            Heb je een UitPAS? Geef dan hieronder je UitPAS nummer in. Wij passen dan het juiste tarief toe.
            @if(organisation()->getContactOptionsText())
                <br />Ondervind je problemen? {!! organisation()->getContactOptionsText() !!}
            @endif
        </p>

        <div class="form-group">
            {{ Form::text('uitpas', null, [ 'class' => 'form-control', 'placeholder' => 'Schrijf hier je UitPAS nummer' ]) }}
        </div>
    @endif

    <p>{{ Form::submit('Betalen', array('class' => 'btn btn-primary')) }}</p>

    {{ Form::close() }}

@endsection
