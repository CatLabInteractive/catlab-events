<section id="b-{{ $block['id'] }}" class="cms-block cms-hero cms-hero-{{ $data['align'] }}">
    <div class="banner-item bg-overlay cms-hero-inner"
         @if($data['image'])
            style="background-image:url('{{ $data['image']->getUrl([ 'width' => 1920 ]) }}')"
         @endif
    >
        <div class="container">
            <div class="banner-content">
                @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '', 'level' => $headingLevel ?? 2, 'class' => 'banner-title' ])

                @if(!empty($data['subtitle']))
                    <p class="banner-subtitle cms-text">{{ $data['subtitle'] }}</p>
                @endif

                @include('cms.blocks._buttons', [ 'buttons' => $data['buttons'] ])
            </div>
        </div>
    </div>
</section>
