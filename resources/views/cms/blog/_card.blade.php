{{--
    One post in a list (blog index, latest_posts block, series page): the
    image on a colour field, then date, title and excerpt. The whole card
    is the link.
    $post: App\Cms\Blog::summary() [ title, url, excerpt, author, published_at, image ]
    $locale: the locale to format the date in.
    $headingLevel: 2 or 3 (default 3).
--}}
@php($tag = 'h' . (isset($headingLevel) && (int) $headingLevel === 2 ? 2 : 3))
<a class="kv-post {{ $fieldClass ?? '' }}" href="{{ $post['url'] }}">
    <span class="kv-post-image">
        @if($post['image'])
            <img src="{{ $post['image'] }}" alt="" loading="lazy" />
        @endif
    </span>
    <span class="kv-card-date">
        @if($post['published_at'])
            <time datetime="{{ $post['published_at']->toIso8601String() }}">{{ $post['published_at']->copy()->locale($locale)->translatedFormat('j F Y') }}</time>
            &middot;
        @endif
        <span class="kv-cms-post-author">{{ $post['author'] }}</span>
    </span>
    <{{ $tag }} class="kv-card-title">{{ $post['title'] }}</{{ $tag }}>
    @if($post['excerpt'])
        <span class="kv-post-excerpt">{{ $post['excerpt'] }}</span>
    @endif
</a>
