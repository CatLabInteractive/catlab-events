{{--
    Quotes. Without logos: a grid of cards. With logos (layout list): one
    quote per row, the logo next to the quote, which sits in a colour panel
    with the author as its heading.
--}}
<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-reviews kv-cms-reviews-{{ $data['layout'] ?? 'grid' }}">
    <div class="kv-wrap">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <div class="kv-reviews">
            @foreach($data['items'] as $item)
                @php($reviewColour = $item['colour'] ?? null)
                @php($reviewClass = $reviewColour === 'peach' ? 'kv-cms-panel-peach' : (isset($palette) ? $palette->getClassFor($reviewColour !== null ? (int) $reviewColour : ($fieldIndex ?? 0) + $loop->index) : ''))
                @if(($data['layout'] ?? 'grid') === 'list')
                    <div class="kv-review-row">
                        <div class="kv-review-logo">
                            @if($item['image'])
                                <img src="{{ $item['image']->getUrl([ 'width' => 400 ]) }}" alt="{{ $item['source'] ?? '' }}" loading="lazy">
                            @endif
                        </div>
                        <blockquote class="kv-review kv-review-panel {{ $reviewClass }}">
                            @if(!empty($item['author']))
                                <h3 class="kv-review-author">{{ $item['author'] }}</h3>
                            @endif
                            <p class="kv-review-quote kv-cms-lines">{{ $item['quote'] ?? '' }}</p>
                            @if(!empty($item['source']))
                                <footer class="kv-review-by">
                                    @if($item['url'])
                                        <cite><a href="{{ $item['url'] }}">{{ $item['source'] }}</a></cite>
                                    @else
                                        <cite>{{ $item['source'] }}</cite>
                                    @endif
                                </footer>
                            @endif
                        </blockquote>
                    </div>
                @else
                    <blockquote class="kv-review {{ $reviewClass }}">
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
                @endif
            @endforeach
        </div>
    </div>
</section>
