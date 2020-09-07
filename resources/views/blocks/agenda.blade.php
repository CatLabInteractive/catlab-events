<h3>Quiz kalender</h3>
@if(!empty($cities))
    <p>
        Ben je op zoek naar een leuke groepsactiviteit in {{ implode($cities, ', ') }}
        of weet je even niet wat te doen?
        Wij organiseren vast de leukste quizzes van {{ implode($cities, ', ') }}!
    </p>
@endif

@if (count($events) === 0)
    <p>Er zijn nog geen evenementen gepland.</p>
@else
    @component('blocks.eventtable', [ 'events' => $events ])
    @endcomponent
@endif

@if(isset($pastEvents))
    <h3>Voorbije edities</h3>
    @if (count($pastEvents) === 0)
        <p>Er zijn geen voorbije edities.</p>
    @else
        @component('blocks.eventtable', [ 'events' => $pastEvents ])
        @endcomponent
    @endif
@endif

@section('critical-css')
    <style>{!! file_get_contents(resource_path('criticalcss/calendar_critical.min.css')) !!}</style>
@endsection