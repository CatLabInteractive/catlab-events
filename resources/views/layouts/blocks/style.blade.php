<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;700&display=swap" rel="stylesheet" />
<link href="{{ mix('css/app.css') }}" rel="stylesheet" type="text/css" />
@if(organisation())
    {{-- Series colours of this organisation; only hex values come through (App\Tools\ColourPalette). --}}
    <style>{!! organisation()->getColourPalette()->toCss() !!}</style>
@endif
