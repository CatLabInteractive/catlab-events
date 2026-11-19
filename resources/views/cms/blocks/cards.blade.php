{{--
    Each card takes its own colour, or the next colour of the palette starting
    at this section's colour. Styles: tiles, people (round photo, a team),
    panels (the whole card in its colour).
--}}
<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-cards kv-cms-cards-{{ $data['style'] ?? 'tiles' }}">
    <div class="kv-wrap">
        @if(!empty($data['title']) || !empty($data['intro']))
            <div class="kv-cms-head">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])
                @if(!empty($data['intro']))
                    <p class="kv-cms-intro kv-cms-lines">{{ $data['intro'] }}</p>
                @endif
            </div>
        @endif

        <div class="kv-tiles kv-tiles-{{ $data['columns'] }}">
            @foreach($data['items'] as $item)
                @php($cardColour = $item['colour'] ?? null)
                <div class="kv-tile {{ $cardColour === 'peach' ? 'kv-cms-panel-peach' : (isset($palette) ? $palette->getClassFor($cardColour !== null ? (int) $cardColour : ($fieldIndex ?? 0) + $loop->index) : '') }}">
                    @if($item['image'])
                        <span class="kv-tile-image">
                            <img src="{{ $item['image']->getUrl([ 'width' => 640 ]) }}" alt="" loading="lazy">
                        </span>
                    @elseif($item['icon'])
                        <span class="kv-tile-icon"><i class="fa fa-{{ $item['icon'] }}" aria-hidden="true"></i></span>
                    @else
                        <span class="kv-tile-dot" aria-hidden="true"></span>
                    @endif

                    <h3 class="kv-tile-title">
                        @if($item['url'])
                            <a href="{{ $item['url'] }}">{{ $item['title'] ?? '' }}</a>
                        @else
                            {{ $item['title'] ?? '' }}
                        @endif
                    </h3>

                    @if(!empty($item['text']))
                        <p class="kv-tile-text kv-cms-lines">{{ $item['text'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
