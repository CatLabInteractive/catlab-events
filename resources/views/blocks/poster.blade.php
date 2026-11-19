{{--
    A poster of any aspect ratio. The frame takes the poster's own ratio
    (clamped, so a banner or a very tall poster doesn't blow up the layout)
    and the image is never cropped. Whatever sits behind it (a colour field)
    fills the space around it. Clicking opens the full poster in the lightbox.

    @param \CatLab\CentralStorage\Client\Models\Asset $poster
    @param string $alt
    @param int    $width   width of the image to load (default 800)
    @param string $class   extra classes on the frame
    @param bool   $lazy    lazy-load the image
    @param string $sizes   the sizes attribute for the srcset
--}}
<?php
    $posterWidth = $width ?? 800;
    $posterRatio = ($poster->width && $poster->height) ? $poster->width / $poster->height : null;
    $frameRatio = $posterRatio ? max(0.7, min(1.9, $posterRatio)) : 1;
    // Let the browser pick a size that fits the screen; never offer more than the original.
    $srcsetWidths = array_filter([ 480, 800, 1200, 1600 ], function ($w) use ($poster) {
        return !$poster->width || $w <= max(480, $poster->width);
    });
?>
<a class="kv-poster image-popup {{ $class ?? '' }}"
   href="{{ $poster->getUrl([ 'width' => 1600 ]) }}"
   style="--kv-poster-ratio: {{ round($frameRatio, 4) }}"
   aria-label="Poster '{{ $alt }}' vergroten">
    <img src="{{ $poster->getUrl([ 'width' => $posterWidth ]) }}"
         srcset="@foreach($srcsetWidths as $w){{ $loop->first ? '' : ', ' }}{{ $poster->getUrl([ 'width' => $w ]) }} {{ $w }}w @endforeach"
         sizes="{{ $sizes ?? '(min-width: 768px) 50vw, 100vw' }}"
         @if($poster->width && $poster->height) width="{{ $poster->width }}" height="{{ $poster->height }}" @endif
         alt="Poster '{{ $alt }}'"
         @if(!empty($lazy)) loading="lazy" @endif />
</a>
