{{--
    light: the peach band; primary: this section's colour field with circles;
    dark: ink with light text.
--}}
<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-cta kv-cms-cta-{{ $data['background'] }} @if($data['background'] === 'light') kv-band @endif {{ $data['background'] === 'primary' ? ($fieldClass ?? '') : '' }}">
    <div class="kv-wrap">
        <div class="kv-cms-cta-inner">
            @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

            @if(!empty($data['text']))
                <p class="kv-cms-intro kv-cms-lines">{{ $data['text'] }}</p>
            @endif

            @include('cms.blocks._buttons', [ 'buttons' => $data['buttons'] ])
        </div>
    </div>
</section>
