<section id="b-{{ $block['id'] }}" class="cms-block cms-cta cms-cta-{{ $data['background'] }}">
    <div class="container text-center">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        @if(!empty($data['text']))
            <p class="cms-text">{{ $data['text'] }}</p>
        @endif

        @include('cms.blocks._buttons', [ 'buttons' => $data['buttons'] ])
    </div>
</section>
