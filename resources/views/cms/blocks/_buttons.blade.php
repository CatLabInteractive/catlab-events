{{-- $buttons: list of [label, url, style] already checked by SafeUrl in prepare() --}}
@if(count($buttons) > 0)
    <p class="cms-buttons">
        @foreach($buttons as $button)
            <a href="{{ $button['url'] }}" class="btn {{ ($button['style'] ?? '') === 'primary' ? 'btn-primary' : 'btn-default' }}">{{ $button['label'] }}</a>
        @endforeach
    </p>
@endif
