{{--
    A poster of any aspect ratio. The frame takes the poster's own ratio
    (clamped, so a banner or a very tall poster doesn't blow up the layout),
    the image is never cropped, and a blurred copy of it fills any space
    left around it. Clicking opens the full poster in the lightbox.

    @param \CatLab\CentralStorage\Client\Models\Asset $poster
    @param string $alt
    @param int    $width   width of the image to load (default 800)
    @param string $class   extra classes on the frame
--}}
<?php
    $posterWidth = $width ?? 800;
    $posterRatio = ($poster->width && $poster->height) ? $poster->width / $poster->height : null;
    $frameRatio = $posterRatio ? max(0.7, min(1.9, $posterRatio)) : 1;
    $posterSrc = $poster->getUrl([ 'width' => $posterWidth ]);
?>
<a class="ev-poster image-popup {{ $class ?? '' }}"
   href="{{ $poster->getUrl([ 'width' => 1600 ]) }}"
   style="--ev-poster-ratio: {{ round($frameRatio, 4) }}"
   aria-label="Poster '{{ $alt }}' vergroten">
    <span class="ev-poster-backdrop" style="background-image: url('{{ $poster->getUrl([ 'width' => 100 ]) }}')" aria-hidden="true"></span>
    <img src="{{ $posterSrc }}"
         @if($poster->width && $poster->height) width="{{ $poster->width }}" height="{{ $poster->height }}" @endif
         alt="Poster '{{ $alt }}'"
         @if(!empty($lazy)) loading="lazy" @endif />
    <span class="ev-poster-zoom" aria-hidden="true"><i class="fa fa-expand"></i></span>
</a>
