<section id="b-{{ $block['id'] }}" class="cms-block cms-rich-text">
    <div class="container">
        {{-- html is sanitised on save (RichText::htmlFields()) --}}
        <div class="cms-prose">{!! $data['html'] ?? '' !!}</div>
    </div>
</section>
