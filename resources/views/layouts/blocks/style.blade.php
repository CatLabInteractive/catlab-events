{{-- Jost is self-hosted (@font-face in app.scss); preload it so text does not reflow late. --}}
<link rel="preload" href="/webfonts/jost-latin.woff2" as="font" type="font/woff2" crossorigin />
<link href="{{ mix('css/app.css') }}" rel="stylesheet" type="text/css" />
<script src="{{ mix('js/kv.js') }}" defer></script>
@if(organisation())
    {{-- Series colours of this organisation; only hex values come through (App\Tools\ColourPalette). --}}
    <style>{!! organisation()->getColourPalette()->toCss() !!}</style>
@endif
