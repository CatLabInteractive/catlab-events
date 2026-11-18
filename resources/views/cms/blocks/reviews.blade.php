<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-reviews">
    <div class="kv-wrap">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <div class="kv-reviews">
            @foreach($data['items'] as $item)
                <blockquote class="kv-review {{ isset($palette) ? $palette->getClassFor(($fieldIndex ?? 0) + $loop->index) : '' }}">
                    <p class="kv-review-quote kv-cms-lines">{{ $item['quote'] ?? '' }}</p>
                    @if(!empty($item['author']) || !empty($item['source']))
                        <footer class="kv-review-by">
                            {{ $item['author'] ?? '' }}@if(!empty($item['author']) && !empty($item['source'])), @endif
                            @if(!empty($item['source']))
                                @if($item['url'])
                                    <cite><a href="{{ $item['url'] }}">{{ $item['source'] }}</a></cite>
                                @else
                                    <cite>{{ $item['source'] }}</cite>
                                @endif
                            @endif
                        </footer>
                    @endif
                </blockquote>
            @endforeach
        </div>
    </div>
</section>
