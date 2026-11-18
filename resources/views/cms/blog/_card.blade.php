{{--
    One post in a list (blog index, latest_posts block, series page).
    $post: App\Cms\Blog::summary() [ title, url, excerpt, author, published_at, image ]
    $locale: the locale to format the date in.
    $headingLevel: 2 or 3 (default 3).
--}}
@php($tag = 'h' . (isset($headingLevel) && (int) $headingLevel === 2 ? 2 : 3))
<div class="latest-post cms-post-card">
    @if($post['image'])
        <div class="latest-post-media">
            <a href="{{ $post['url'] }}" class="latest-post-img">
                <img class="img-fluid" src="{{ $post['image'] }}" alt="" loading="lazy" />
            </a>
        </div>
    @endif
    <div class="post-body">
        <{{ $tag }} class="post-title"><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></{{ $tag }}>
        <p class="cms-post-meta">
            @if($post['published_at'])
                <time datetime="{{ $post['published_at']->toIso8601String() }}">{{ $post['published_at']->copy()->locale($locale)->translatedFormat('j F Y') }}</time>
                &middot;
            @endif
            <span class="cms-post-author">{{ $post['author'] }}</span>
        </p>
        @if($post['excerpt'])
            <p class="cms-post-excerpt">{{ $post['excerpt'] }}</p>
        @endif
        <a href="{{ $post['url'] }}" class="cms-read-more">{{ __('cms.read_more', [], $locale) }} &rarr;</a>
    </div>
</div>
