<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-video">
    <div class="kv-wrap">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        {{-- embed_url is rebuilt from the YouTube id in Video::prepare() --}}
        <figure class="kv-video">
            <iframe src="{{ $data['embed_url'] }}" title="{{ $data['title'] ?: 'Video' }}"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            @if(!empty($data['caption']))
                <figcaption class="kv-cms-caption">{{ $data['caption'] }}</figcaption>
            @endif
        </figure>
    </div>
</section>
