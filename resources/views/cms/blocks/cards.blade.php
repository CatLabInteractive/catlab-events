{{-- Each card takes the next colour of the palette, starting at this section's colour. --}}
<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-cards">
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
                <div class="kv-tile {{ isset($palette) ? $palette->getClassFor(($fieldIndex ?? 0) + $loop->index) : '' }}">
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
