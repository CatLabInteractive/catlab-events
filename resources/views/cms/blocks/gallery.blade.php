{{--
    A photo gallery. Each photo links to its large version; kv.js opens it in
    the lightbox and steps through the gallery (data-kv-gallery). Layouts:
    grid (even 3:2 tiles), mosaic (each photo in its own shape) and featured
    (even tiles, the first one big). With `visible`, the photos after it are
    hidden behind a "show all" button until kv.js runs; without JS they all show.
--}}
@php
    $layout = $data['layout'];
    $visible = $data['visible'];
    $count = count($data['items']);
    $galleryId = 'g-' . $block['id'];
    // The width a tile takes at most, for the srcset's sizes.
    $sizes = $layout === 'mosaic'
        ? '(min-width: 992px) 25vw, (min-width: 768px) 33vw, 50vw'
        : '(min-width: 992px) 33vw, 50vw';
@endphp
<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-gallery">
    <div class="kv-wrap">
        @if(!empty($data['title']) || !empty($data['intro']))
            <div class="kv-cms-head">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])
                @if(!empty($data['intro']))
                    <p class="kv-cms-intro kv-cms-lines">{{ $data['intro'] }}</p>
                @endif
            </div>
        @endif

        <ul class="kv-gallery kv-gallery-{{ $layout }}" id="{{ $galleryId }}" @if($visible) data-kv-gallery-visible="{{ $visible }}" @endif>
            @foreach($data['items'] as $item)
                @php
                    $image = $item['image'];
                    $alt = $item['caption'] ?? '';
                    $big = $layout === 'featured' && $loop->first;
                    $srcset = array_filter([ 400, 640, 960, 1280 ], function ($w) use ($image) {
                        return !$image->width || $w <= max(400, $image->width);
                    });
                @endphp
                <li class="kv-gallery-item{{ $big ? ' kv-gallery-big' : '' }}">
                    <a class="kv-gallery-link" href="{{ $image->getUrl([ 'width' => 1920 ]) }}"
                       data-kv-gallery="{{ $galleryId }}"
                       @if($item['caption']) data-kv-caption="{{ $item['caption'] }}" @endif
                       aria-label="Foto {{ $loop->iteration }} van {{ $count }} vergroten{{ $item['caption'] ? ': ' . $item['caption'] : '' }}">
                        <img src="{{ $image->getUrl([ 'width' => $big ? 1280 : 640 ]) }}"
                             srcset="@foreach($srcset as $w){{ $loop->first ? '' : ', ' }}{{ $image->getUrl([ 'width' => $w ]) }} {{ $w }}w @endforeach"
                             sizes="{{ $big ? '(min-width: 768px) 66vw, 100vw' : $sizes }}"
                             @if($image->width && $image->height) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                             alt="{{ $alt }}" loading="lazy" decoding="async">
                    </a>
                    @if($item['caption'] && $layout === 'mosaic')
                        <span class="kv-gallery-caption">{{ $item['caption'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($visible)
            <button type="button" class="kv-btn kv-btn-small kv-gallery-more" data-kv-gallery-more="{{ $galleryId }}" hidden>
                <span>Toon alle {{ $count }} foto's</span><span class="kv-btn-dot" aria-hidden="true"></span>
            </button>
        @endif
    </div>
</section>
