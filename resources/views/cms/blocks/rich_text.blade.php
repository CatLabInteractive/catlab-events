<section id="b-{{ $block['id'] }}" class="kv-sec kv-cms-rich-text">
    <div class="kv-wrap kv-wrap-prose">
        {{-- html is sanitised on save (RichText::htmlFields()) --}}
        <div class="kv-prose">{!! $data['html'] ?? '' !!}</div>
    </div>
</section>
