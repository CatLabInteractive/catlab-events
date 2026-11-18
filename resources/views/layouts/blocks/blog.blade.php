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
    <section class="kv-sec" id="blog">
        <div class="kv-wrap">
            <h2 class="kv-sec-title">{{ __('cms.more_from', [ 'organisation' => organisation()->name ], $blogLocale) }} <small><a class="kv-cms-link" href="{{ \App\Cms\Blog::indexUrl($blogLocale) }}">{{ __('cms.blog', [], $blogLocale) }}</a></small></h2>

            <div class="kv-posts">
                @foreach($blogPosts as $post)
                    @include('cms.blog._card', [ 'post' => $post, 'locale' => $blogLocale, 'headingLevel' => 3, 'fieldClass' => organisation()->getColourPalette()->getClassFor($loop->index) ])
                @endforeach
            </div>
        </div>
    </section>
@endif
