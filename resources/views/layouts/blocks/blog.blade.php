<?php
    $feedUrl = organisation()->blog_rss_url;
    if ($feedUrl) {
        $feed = \Feeds::make($feedUrl);
    }
?>

@if($feedUrl)
    <section class="kv-sec" id="blog">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">Meer {{ organisation()->name }} <small>{{ organisation()->website_url }}</small></h2>

            <div class="kv-posts">
                @foreach($feed->get_items(0, 3) as $item)
                    <?php $enclosure = $item->get_enclosure(); ?>
                    <a class="kv-post" href="{{ $item->get_permalink() }}">
                        @if($enclosure && $enclosure->get_link())
                            <span class="kv-post-image">
                                <img src="{{ \CentralStorage::getPublicAssetUrl($enclosure->get_link(), [ 'width' => 480 ]) }}" alt="" loading="lazy" />
                            </span>
                        @endif
                        <span class="kv-card-date">{{ $item->get_date('j M Y') }}</span>
                        <span class="kv-card-title">{!! $item->get_title() !!}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
