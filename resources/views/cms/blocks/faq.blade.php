<section id="b-{{ $block['id'] }}" class="{{ $bandClass ?? '' }} kv-sec kv-cms-faq {{ $fieldClass ?? '' }}">
    <div class="kv-wrap">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        <div class="kv-faq">
            @foreach($data['items'] as $item)
                <details class="kv-faq-item">
                    <summary>{{ $item['question'] ?? '' }}</summary>
                    {{-- answer is sanitised on save (Faq::htmlFields()) --}}
                    <div class="kv-prose">{!! $item['answer'] ?? '' !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
