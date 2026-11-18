@extends('layouts/home')

@section('title'){{ $pageTitle }}@endsection

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

    <div class="cms-page cms-page-{{ $translation->page_id }}">

        @if(!$startsWithHero)
            <section class="cms-page-header">
                <div class="container">
                    <h1 class="cms-page-title">{{ $translation->title }}</h1>
                </div>
            </section>
        @endif

        @foreach($blocks as $block)
            @include($block['view'], [
                'data' => $block['data'],
                'block' => $block['block'],
                'headingLevel' => ($loop->first && $startsWithHero) ? 1 : 2,
            ])
        @endforeach

    </div>

@endsection
