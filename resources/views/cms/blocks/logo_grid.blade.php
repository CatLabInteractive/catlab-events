<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-logo-grid">
    <div class="kv-wrap">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <ul class="kv-logos">
            @foreach($data['items'] as $item)
                <li class="kv-logo">
                    @if($item['url'])<a href="{{ $item['url'] }}">@endif
                    @if($item['image'])
                        <img src="{{ $item['image']->getUrl([ 'width' => 320 ]) }}" alt="{{ $item['name'] ?? '' }}" loading="lazy">
                    @else
                        <span class="kv-logo-name">{{ $item['name'] ?? '' }}</span>
                    @endif
                    @if($item['url'])</a>@endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
