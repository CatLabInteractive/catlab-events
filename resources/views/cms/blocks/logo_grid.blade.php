<section id="b-{{ $block['id'] }}" class="cms-block cms-logo-grid">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <ul class="cms-logos">
            @foreach($data['items'] as $item)
                <li class="cms-logo">
                    @if($item['url'])<a href="{{ $item['url'] }}">@endif
                    @if($item['image'])
                        <img src="{{ $item['image']->getUrl([ 'width' => 320 ]) }}" alt="{{ $item['name'] ?? '' }}" loading="lazy">
                    @else
                        <span class="cms-logo-name">{{ $item['name'] ?? '' }}</span>
                    @endif
                    @if($item['url'])</a>@endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
