{{-- Renders nothing until there are posts (the blog arrives in a later phase). --}}
@if(count($data['posts']) > 0)
    <section id="b-{{ $block['id'] }}" class="cms-block cms-latest-posts">
        <div class="container">
            @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

            <ul class="cms-posts">
                @foreach($data['posts'] as $post)
                    <li><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
