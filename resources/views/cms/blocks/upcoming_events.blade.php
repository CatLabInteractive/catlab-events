<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-upcoming-events">
    <div class="kv-wrap">
        @if(!empty($data['title']) || !empty($data['intro']))
            <div class="kv-cms-head">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])
                @if(!empty($data['intro']))
                    <p class="kv-cms-intro kv-cms-lines">{{ $data['intro'] }}</p>
                @endif
            </div>
        @endif

        @if(count($data['events']) > 0)
            <div class="kv-cards">
                @foreach($data['events'] as $cardEvent)
                    @include('blocks.event-card', [ 'cardEvent' => $cardEvent ])
                @endforeach
            </div>
        @elseif(!empty($data['empty_text']))
            <p class="kv-cms-empty">{{ $data['empty_text'] }}</p>
        @endif
    </div>
</section>
