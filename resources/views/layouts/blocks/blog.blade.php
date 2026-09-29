{{--
    Latest posts of the organisation's own blog (default locale), shown on
    the series page. Replaces the old RSS block (blog_rss_url). Renders
    nothing when the organisation has no visible posts.
--}}
@php
    $blogLocale = config('cms.default_locale');
    $blogPosts = organisation() ? app(\App\Cms\Blog::class)->latest(organisation(), $blogLocale, 3) : [];
@endphp

@if(count($blogPosts) > 0)
    <!-- Blog -->
    <section id="blog" class="blog solid-bg">
        <div class="container">
            <div class="row text-center">
                <h2 class="section-title">{{ __('cms.more_from', [ 'organisation' => organisation()->name ], $blogLocale) }}</h2>
                <h3 class="section-sub-title"><a href="{{ \App\Cms\Blog::indexUrl($blogLocale) }}">{{ __('cms.blog', [], $blogLocale) }}</a></h3>
            </div><!--/ Title row end -->

            <div class="row">
                @foreach($blogPosts as $post)
                    <div class="col-md-4 col-xs-12">
                        @include('cms.blog._card', [ 'post' => $post, 'locale' => $blogLocale, 'headingLevel' => 3 ])
                    </div>
                @endforeach
            </div><!--/ Content row end -->
        </div><!--/ Container end -->
    </section>
@endif
