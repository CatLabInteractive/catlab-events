<?php $columnClass = [ 2 => 'col-md-6', 3 => 'col-md-4', 4 => 'col-md-3' ][$data['columns']] ?? 'col-md-4'; ?>
<section id="b-{{ $block['id'] }}" class="cms-block cms-cards">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        @if(!empty($data['intro']))
            <p class="cms-intro cms-text">{{ $data['intro'] }}</p>
        @endif

        <div class="row">
            @foreach($data['items'] as $item)
                <div class="{{ $columnClass }} col-sm-12 cms-card-column">
                    <div class="cms-card">
                        @if($item['image'])
                            <img src="{{ $item['image']->getUrl([ 'width' => 640 ]) }}" alt="{{ $item['title'] ?? '' }}" class="img-fluid cms-card-image" loading="lazy">
                        @elseif($item['icon'])
                            <i class="fa fa-{{ $item['icon'] }} cms-card-icon" aria-hidden="true"></i>
                        @endif

                        <h3 class="cms-card-title">
                            @if($item['url'])
                                <a href="{{ $item['url'] }}">{{ $item['title'] ?? '' }}</a>
                            @else
                                {{ $item['title'] ?? '' }}
                            @endif
                        </h3>

                        @if(!empty($item['text']))
                            <p class="cms-text">{{ $item['text'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
