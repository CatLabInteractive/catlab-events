{{--
    Text next to an image (uncropped on a colour field) or a YouTube video.
    With panel_colour the text sits in a panel of that colour.
--}}
@php($panelClass = $data['panel_colour'] === null ? '' : ('kv-cms-panel ' . ($data['panel_colour'] === 'peach' ? 'kv-cms-panel-peach' : (isset($palette) ? $palette->getClassFor((int) $data['panel_colour']) : ''))))
<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-text-image kv-cms-image-{{ $data['image_position'] === 'left' ? 'left' : 'right' }} {{ $fieldClass ?? '' }}">
    <div class="kv-wrap">
        <div class="kv-cms-split">
            <div class="kv-cms-split-text {{ $panelClass }}">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

                {{-- html is sanitised on save (TextImage::htmlFields()) --}}
                <div class="kv-prose">{!! $data['html'] ?? '' !!}</div>

                @if($data['button'])
                    @include('cms.blocks._buttons', [ 'buttons' => [ array_merge($data['button'], [ 'style' => 'primary' ]) ] ])
                @endif
            </div>
            @if($data['embed_url'])
                {{-- embed_url is rebuilt from the YouTube id in TextImage::prepare() --}}
                <figure class="kv-video kv-cms-split-video">
                    <iframe src="{{ $data['embed_url'] }}" title="{{ $data['title'] ?: 'Video' }}"
                            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </figure>
            @elseif($data['image'])
                <div class="kv-cms-split-image">
                    @include('blocks.image', [ 'image' => $data['image'], 'sizes' => '(min-width: 992px) 50vw, 100vw', 'alt' => $data['title'] ?? '', 'lazy' => true ])
                </div>
            @endif
        </div>
    </div>
</section>
