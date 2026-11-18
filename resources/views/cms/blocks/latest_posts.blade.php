{{-- Renders nothing when the organisation has no visible posts in this locale. --}}
@if(count($data['posts']) > 0)
    <section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-latest-posts">
        <div class="kv-wrap">
            <div class="kv-cms-head kv-cms-head-row">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])
                <a class="kv-cms-link" href="{{ \App\Cms\Blog::indexUrl($data['locale']) }}">{{ __('cms.blog', [], $data['locale']) }}</a>
            </div>

            <div class="kv-posts">
                @foreach($data['posts'] as $post)
                    @include('cms.blog._card', [ 'post' => $post, 'locale' => $data['locale'], 'headingLevel' => 3, 'fieldClass' => isset($palette) ? $palette->getClassFor(($fieldIndex ?? 0) + $loop->index) : '' ])
                @endforeach
            </div>
        </div>
    </section>
@endif
