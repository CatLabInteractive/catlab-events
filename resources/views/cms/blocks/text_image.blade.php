<section id="b-{{ $block['id'] }}" class="cms-block cms-text-image">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 @if($data['image_position'] === 'left') order-md-2 @endif">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

                {{-- html is sanitised on save (TextImage::htmlFields()) --}}
                <div class="cms-prose">{!! $data['html'] ?? '' !!}</div>

                @if($data['button'])
                    <p class="cms-buttons">
                        <a href="{{ $data['button']['url'] }}" class="btn btn-primary">{{ $data['button']['label'] }}</a>
                    </p>
                @endif
            </div>
            <div class="col-md-6 @if($data['image_position'] === 'left') order-md-1 @endif">
                @if($data['image'])
                    <img src="{{ $data['image']->getUrl([ 'width' => 1280 ]) }}" alt="{{ $data['title'] ?? '' }}" class="img-fluid cms-image" loading="lazy">
                @endif
            </div>
        </div>
    </div>
</section>
