@if($event->team_size)
    <p class="alert alert-info">
        Deze quiz speel je in teams van maximaal {{ $event->team_size }} deelnemers.
    </p>
@else
    <p class="alert alert-info">
        Aan deze quiz kan je ofwel in team, ofwel individueel deelnemen. In beide gevallen moet je echter een unieke
        teamnaam verzinnen die gebruikt zal worden in het spel.
    </p>
@endif
