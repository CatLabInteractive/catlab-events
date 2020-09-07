<?php

    $feedUrl = organisation()->blog_rss_url;
    if ($feedUrl) {
        $feed = \Feeds::make($feedUrl);
    }

?>

@if($feedUrl)
    <!-- Blog -->
    <section id="blog" class="blog solid-bg">
        <div class="container">
            <div class="row text-center">
                <h2 class="section-title">Meer {{organisation()->name}}</h2>
                <h3 class="section-sub-title">{{organisation()->website_url}}</h3>
            </div><!--/ Title row end -->

            <div class="row">
                @foreach($feed->get_items(0, 3) as $item)
                <div class="col-md-4 col-xs-12">
                    <div class="latest-post">
                        <div class="latest-post-media">
                            <?php
                                $enclosure = $item->get_enclosure();
                            ?>
                            @if($enclosure && $enclosure->get_link())
                                <a href="{{ $item->get_permalink() }}" class="latest-post-img">
                                    <img class="img-responsive lazy" data-src="{{ \CentralStorage::getPublicAssetUrl($enclosure->get_link(), [ 'width' => 360 ]) }}" alt="Blog image" title="{{ $item->get_title() }}" />
                                </a>

                                <div class="post-item-date">
                                    <span class="day">{{ $item->get_date('d') }}</span>
                                    <span class="month">{{ $item->get_date('M') }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="post-body">
                            <h4 class="post-title">
                                <a href="{{ $item->get_permalink() }}">{!! $item->get_title()  !!}</a>
                            </h4>

                            {!! $item->get_description() !!}
                        </div>
                    </div><!-- Latest post end -->
                </div><!-- 1st post col end -->
                @endforeach

            </div><!--/ Content row end -->
        </div><!--/ Container end -->
    </section>
@endif
