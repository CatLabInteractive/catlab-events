{{--
    $buttons: list of [label, url, style] already checked by SafeUrl in prepare().
    A primary button is the dark Kleurveld button with the dot; the others are
    underlined links next to it.
--}}
@if(count($buttons) > 0)
    <div class="kv-cms-buttons">
        @foreach($buttons as $button)
            @if(($button['style'] ?? '') === 'primary')
                <a href="{{ $button['url'] }}" class="kv-btn"><span>{{ $button['label'] }}</span><span class="kv-btn-dot" aria-hidden="true">&rarr;</span></a>
            @else
                <a href="{{ $button['url'] }}" class="kv-cms-link">{{ $button['label'] }}</a>
            @endif
        @endforeach
    </div>
@endif
