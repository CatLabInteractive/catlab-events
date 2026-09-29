{{-- $alternates: locale => absolute URL of every published translation. --}}
@if(count($alternates) > 0)
    @foreach($alternates as $locale => $url)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}" />
    @endforeach
    @if(isset($alternates[config('cms.default_locale')]))
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[config('cms.default_locale')] }}" />
    @endif
@endif
