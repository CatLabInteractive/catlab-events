<section id="b-{{ $block['id'] }}" class="cms-block cms-faq">
    <div class="container">
        @include('cms.blocks._heading', [ 'text' => $data['title'] ?? '' ])

        @foreach($data['items'] as $item)
            <details class="cms-faq-item">
                <summary>{{ $item['question'] ?? '' }}</summary>
                {{-- answer is sanitised on save (Faq::htmlFields()) --}}
                <div class="cms-prose">{!! $item['answer'] ?? '' !!}</div>
            </details>
        @endforeach
    </div>
</section>
