{{-- Renders nothing when the organisation has no visible posts in this locale. --}}
@if(count($data['posts']) > 0)
    <section id="b-{{ $block['id'] }}" class="cms-block cms-latest-posts">
        <div class="container">
            @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

            <div class="row cms-posts">
                @foreach($data['posts'] as $post)
                    <div class="col-md-6 col-lg-4 mb-4">
                        @include('cms.blog._card', [ 'post' => $post, 'locale' => $data['locale'], 'headingLevel' => 3 ])
                    </div>
                @endforeach
            </div>

            <p class="cms-latest-posts-more">
                <a href="{{ \App\Cms\Blog::indexUrl($data['locale']) }}">{{ __('cms.blog', [], $data['locale']) }} &rarr;</a>
            </p>
        </div>
    </section>
@endif
