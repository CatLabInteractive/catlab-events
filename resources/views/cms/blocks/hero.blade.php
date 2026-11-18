{{--
    A colour field with circles, like the page heads. An image sits on the
    field uncropped (any ratio), next to the text on wide screens and under
    it on phones; or, with image_style cover, a photo fills the hero under a
    dark veil with white text (like the event hero).
--}}
@php($heroCover = $data['image'] && ($data['image_style'] ?? 'side') === 'cover')
<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-cms-hero kv-cms-hero-{{ $data['align'] }} {{ $fieldClass ?? '' }} @if($heroCover) kv-cms-hero-cover @elseif($data['image']) has-image @endif">
    @if($heroCover)
        <div class="kv-cms-hero-photo" aria-hidden="true">
            <img src="{{ $data['image']->getUrl([ 'width' => 1920 ]) }}" alt="" />
        </div>
    @endif

    <div class="kv-wrap kv-cms-hero-inner">
        <div class="kv-cms-hero-text">
            @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '', 'level' => $headingLevel ?? 2, 'class' => 'kv-cms-hero-title' ])

            @if(!empty($data['subtitle']))
                <p class="kv-cms-hero-subtitle kv-cms-lines">{{ $data['subtitle'] }}</p>
            @endif

            @include('cms.blocks._buttons', [ 'buttons' => $data['buttons'] ])
        </div>

        @if($data['image'] && !$heroCover)
            <div class="kv-cms-hero-image">
                <img src="{{ $data['image']->getUrl([ 'width' => 1280 ]) }}" alt="" />
            </div>
        @endif
    </div>
</section>
