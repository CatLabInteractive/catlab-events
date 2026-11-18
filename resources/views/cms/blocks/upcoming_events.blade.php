<section id="b-{{ $block['id'] }}" class="cms-block cms-upcoming-events">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        @if(!empty($data['intro']))
            <p class="cms-intro cms-text">{{ $data['intro'] }}</p>
        @endif

        @if(count($data['events']) > 0)
            @include('blocks.eventtable', [ 'events' => $data['events'] ])
        @elseif(!empty($data['empty_text']))
            <p class="cms-empty">{{ $data['empty_text'] }}</p>
        @endif
    </div>
</section>
