<html>
<head>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        h1, h2, h3 {
            padding-top: .8em;
        }

        p {
            margin-top: .3em;
        }

        body {
            font-family: "Open Sans", sans-serif;
            line-height: 1.25;
            font-size: .8em;
            padding: 3em 5em;
        }
        table {
            border: 1px solid #ccc;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
            width: 100%;
            margin-top: .5em;
        }
        table caption {
            margin: .5em 0 .75em;
        }
        table tr {
            background: #f8f8f8;
            border: 1px solid #ddd;
            padding: .35em;
        }

        table tr:nth-child(even) {
            background: white;
        }

        table th,
        table td {
            padding: .625em;
            text-align: center;
            font-size: .7em;
        }
        table th {
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        table td:nth-child(1) {
            width: 5%;
        }

        table td:nth-child(2) {
            width: 10%;
        }

        table td:nth-child(3) {
            width: 17%;
        }

        table td:nth-child(4),
        table th:nth-child(4){
            text-align: left;
        }

        table th:nth-child(5),
        table th:nth-child(6),
        table th:nth-child(7),
        table th:nth-child(8),
        table th:nth-child(9) {
            text-align: right;
        }

        table td:nth-child(5),
        table td:nth-child(6),
        table td:nth-child(7),
        table td:nth-child(8),
        table td:nth-child(9) {
            width: 8%;
            text-align: right;
        }

        table thead {display: table-header-group;}
        table tfoot {display: table-header-group;}

        .slide {
            width: 50%;
            float: left;
        }

        .clear {
            clear: both;
        }

        tr.totals td {
            border-top: 2px solid black;
            font-weight: bold;
        }

    </style>
</head>

<body>

<h1>Verlegging</h1>
<p>
    Betreft de online inschrijvingen voor onderstaande evenement georganiseerd door {{$organisation->name}} waarvoor
    betaling verliep via de payment gateway beheerd door {{ config('app.owner.name') }}, {{ config('app.owner.address') }}
</p>

<div class="slide">
    <h2>Organisator</h2>
    <p>
        {{$organisation->getLegalName()}}<br>
        @if(!empty($organisation->address))
            {!!nl2br(e($organisation->address))!!}
        @endif
    </p>

    @if(!empty($organisation->national_id))
        <p>
            {{$organisation->national_id}}
        </p>
    @endif

    @if(!empty($organisation->bank_iban))
        <p>
            IBAN: {{$organisation->bank_iban}}<br>
            BIC: {{$organisation->bank_bic}}
        </p>
    @endif
</div>

<div class="slide">
    <h2>Evenement</h2>
    <p>
        ID: {{$event->id}}
        <br>Naam: {{$event->name}}

        @if($event->startDate)
        <br>Datum: {{$event->startDate->format('d/m/Y')}}
        @endif
    </p>
</div>

<div class="clear"></div>

<h2>Clearing</h2>

<p>
    Het totale bedrag van <strong>{{ toMoney($total) }}</strong> werd geïnd via de payment gateway van {{ config('app.owner.name') }}.
    Dit bedrag wordt integraal overgeschreven naar rekening <strong>{{ $organisation->bank_iban }}</strong> van {{$organisation->name}} daags
    nadat het evenement afgesloten is.

    Transactiekosten en eventuele licentiekosten worden door {{ config('app.owner.name') }} afzonderlijk gefactureerd en dienen
    binnen een termijn van 15 dagen betaald te worden. De transactiekosten hier opgelijst zijn exclusief btw; de meegegeven
    BTW bedragen worden enkel ter illustratie meegegeven en kunnen afwijken van de finale factuur.
</p>

<p>
    {{$organisation->name}} blijft verantwoordelijk voor het innen van BTW voor hun verkopen en verbindt zich ertoe
    een factuur te bieden aan elke deelnemer die daarom vraagt.
</p>

<h2>Betalingen</h2>
<table>
    <thead>
        <tr>
            @foreach($columns as $column)
                <th>
                    {{ $column }}
                </th>
            @endforeach
        </tr>
    </thead>

    @foreach($rows as $row)
        <tr>
            @foreach($row as $value)
                <td>{{ $value }}</td>
            @endforeach
        </tr>
    @endforeach

    <tr class="totals">
        @foreach($columns as $column)
            @if($column === 'Groupname')
                <td class="total">Totaal:</td>
            @else
                <td>
                    @if(isset($totals[$column]))
                        {{ $totals[$column] }}
                    @endif
                </td>
            @endif
        @endforeach
    </tr>
</table>


</body>
</html>
