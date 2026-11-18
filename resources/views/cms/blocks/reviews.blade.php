<section id="b-{{ $block['id'] }}" class="cms-block cms-reviews">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <div class="row">
            @foreach($data['items'] as $item)
                <div class="col-md-6 cms-review-column">
                    <blockquote class="cms-review">
                        <p class="cms-text">{{ $item['quote'] ?? '' }}</p>
                        @if(!empty($item['author']) || !empty($item['source']))
                            <footer>
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
                </div>
            @endforeach
        </div>
    </div>
</section>
