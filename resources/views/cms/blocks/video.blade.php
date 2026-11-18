<section id="b-{{ $block['id'] }}" class="cms-block cms-video">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        {{-- embed_url is rebuilt from the YouTube id in Video::prepare() --}}
        <div class="embed-responsive embed-responsive-16by9">
            <iframe class="embed-responsive-item" src="{{ $data['embed_url'] }}" title="{{ $data['title'] ?: 'Video' }}"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
        </div>

        @if(!empty($data['caption']))
            <p class="cms-caption">{{ $data['caption'] }}</p>
        @endif
    </div>
</section>
