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
    <h3 class="intro-sub-title">{{ $event->getOrderLabel() }}</h3>

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    @if(!$event->isQuizWitzCampaign())
        @if($group)
            <p>Leuk dat jullie erbij willen zijn, {{ $group->name }}!</p>
        @else
            <p>Leuk dat jullie erbij willen zijn!</p>
        @endif
    @endif
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

    @if($showUiTPAS)
        <div class="alert alert-info">
            <h4>UiTPAS</h4>

            {{ Form::open(array('url' => $uitpasAction, 'method' => 'get')) }}

            @if($group)
                {{ Form::hidden('groupId', $group->id) }}
            @endif

            <p>
                Heb je een UitPAS met kansentarief? Geef dan hieronder je UitPAS nummer in. Wij passen dan het juiste tarief toe.
                @if(organisation()->getContactOptionsText())
                    <br />Ondervind je problemen? {!! organisation()->getContactOptionsText() !!}
                @endif
            </p>

            <div class="form-group">
                {{ Form::text('uitpas', $uitpas, [ 'class' => 'form-control', 'placeholder' => 'Schrijf hier je UiTPAS kaartnummer' ]) }}
            </div>

            <p>{{ Form::submit('UiTPAS tarief toepassen', array('class' => 'btn btn-info')) }}</p>
            {{ Form::close()}}

        </div>
    @endif


    {{ Form::open(array('url' => $action)) }}
    @foreach($input as $k => $v)
        {{ Form::hidden($k, $v) }}
    @endforeach

    <div class="invoice">
        <h3>Overzicht</h3>

        @if($uitpas)
            <div class="alert alert-info">
                <p>Tarief voor UiTPAS {{ $uitpas }} wordt toegepast.</p>
            </div>
        @endif

        <table class="table">

            @if($group)
                <tr>
                    <td style="width: 33%">Team</td>
                    <td>{{ $group->name }}</td>
                </tr>
            @endif

            <tr>
                <td>Evenement</td>
                <td>{{ $event->name }}</td>
            </tr>

            @if($ticketCategory->eventDates->count() > 0)
                <tr>
                    <td>Datum</td>
                    <td>{!!
                        $ticketCategory
                            ->eventDates->pluck('startDate')
                            ->map(function($startDate) {
                                return $startDate->format('d/m/Y H:i');
                            })
                            ->join('<br />')
                    !!}</td>
                </tr>
            @endif

            @if($event->venue)
                <tr>
                    <td>Locatie</td>
                    <td><address>{!! $event->venue->getAddressFull('<br />') !!}</address></td>
                </tr>
            @endif

            @if(!$ticketCategory->isFree())

                <tr>
                    <td>Kostprijs</td>
                    <td>
                        {{ $ticketPriceCalculator->getFormattedPrice() }}
                        <span class="small">(incl. {{ $ticketPriceCalculator->getFormattedPriceVat() }} btw)
                    </td>
                </tr>

                <tr>
                    <td>Transactiekosten</td>
                    <td>
                        {{ $ticketPriceCalculator->getFormattedTransactionFee() }}
                        <span class="small">(incl. {{ $ticketPriceCalculator->getFormattedTransactionFeeVat() }} btw)</span>
                    </td>
                </tr>

                <tr class="total">
                    <td>Totaal</td>
                    <td>{{ $ticketPriceCalculator->getFormattedTotalPrice() }}</td>
                </tr>

            @endif
        </table>

    </div>

    @if($ticketCategory->isFree())
        <p>{{ Form::submit('Inschrijven', array('class' => 'btn btn-primary')) }}</p>
    @else
        <p>{{ Form::submit('Betalen', array('class' => 'btn btn-primary')) }}</p>
    @endif

    {{ Form::close() }}

@endsection
