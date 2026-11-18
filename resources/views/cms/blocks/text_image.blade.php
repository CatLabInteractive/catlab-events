<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-text-image kv-cms-image-{{ $data['image_position'] === 'left' ? 'left' : 'right' }} {{ $fieldClass ?? '' }}">
    <div class="kv-wrap">
        <div class="kv-cms-split">
            <div class="kv-cms-split-text">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

                {{-- html is sanitised on save (TextImage::htmlFields()) --}}
                <div class="kv-prose">{!! $data['html'] ?? '' !!}</div>

                @if($data['button'])
                    @include('cms.blocks._buttons', [ 'buttons' => [ array_merge($data['button'], [ 'style' => 'primary' ]) ] ])
                @endif
            </div>
            @if($data['image'])
                <div class="kv-cms-split-image">
                    <img src="{{ $data['image']->getUrl([ 'width' => 1280 ]) }}" alt="{{ $data['title'] ?? '' }}" loading="lazy">
                </div>
            @endif
        </div>
    </div>
</section>
