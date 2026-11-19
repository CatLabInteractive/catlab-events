{{--
    An <img> for a CentralStorage asset with a srcset (never wider than the
    original) and the asset's width and height, so the browser reserves the
    space before the image arrives (no layout shift).

    @param \CatLab\CentralStorage\Client\Models\Asset $image
    @param int    $width     width of the src image (default 1280)
    @param string $sizes     the sizes attribute (default 100vw)
    @param string $alt
    @param bool   $lazy      lazy-load (below the fold)
    @param bool   $priority  fetchpriority=high (the largest image above the fold)
--}}
<?php
    $imageWidth = $width ?? 1280;
    $imageWidths = array_values(array_filter([ 480, 800, 1280, 1920 ], function ($w) use ($image, $imageWidth) {
        return $w <= $imageWidth && (!$image->width || $w <= max(480, $image->width));
    }));
?>
<img src="{{ $image->getUrl([ 'width' => $imageWidth ]) }}"
     srcset="@foreach($imageWidths as $w){{ $loop->first ? '' : ', ' }}{{ $image->getUrl([ 'width' => $w ]) }} {{ $w }}w @endforeach"
     sizes="{{ $sizes ?? '100vw' }}"
     @if($image->width && $image->height) width="{{ $image->width }}" height="{{ $image->height }}" @endif
     alt="{{ $alt ?? '' }}"
     @if(!empty($lazy)) loading="lazy" @endif
     @if(!empty($priority)) fetchpriority="high" @endif />
