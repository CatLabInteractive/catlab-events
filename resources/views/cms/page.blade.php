@extends('layouts/home')

@section('title'){{ $pageTitle }}@endsection
@section('bodyClass')kv-page kv-cms @endsection

@if($description)
    @section('description'){{ $description }}@endsection
@endif

@push('head')
    @include('cms.partials.head')
    @include('cms.partials.alternates', [ 'alternates' => $alternates ])
@endpush

@section('jsonld-content')
    @include('cms.partials.jsonld')
@endsection

@section('content')
    @php($cmsPalette = organisation()->getColourPalette())

    <div class="kv-cms-page kv-cms-page-{{ $translation->page_id }}">

        @if(!$startsWithHero)
            <div class="kv-pagehead {{ $cmsPalette->getClassFor(0) }}">
                <div class="kv-wrap">
                    <h1 class="kv-pagehead-title">{{ $translation->title }}</h1>
                </div>
            </div>
        @endif

        {{-- Each section gets the next colour of the organisation's palette. --}}
        {{-- A block with a colour is a full-width band in that colour. --}}
        @foreach($blocks as $block)
            @php($bandColour = \App\Cms\Blocks\BlockType::colour($block['data']['colour'] ?? null))
            @php($fieldIndex = ($bandColour !== null && $bandColour !== 'peach') ? (int) $bandColour : $loop->index)
            @include($block['view'], [
                'data' => $block['data'],
                'block' => $block['block'],
                'headingLevel' => ($loop->first && $startsWithHero) ? 1 : 2,
                'palette' => $cmsPalette,
                'fieldIndex' => $fieldIndex,
                'fieldClass' => $cmsPalette->getClassFor($fieldIndex),
                'bandClass' => $bandColour === null ? '' : ('kv-cms-band ' . ($bandColour === 'peach' ? 'kv-cms-band-peach' : $cmsPalette->getClassFor($fieldIndex))),
            ])
        @endforeach

    </div>

@endsection
