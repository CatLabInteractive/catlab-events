@extends('layouts/home')

@section('title'){{ __('cms.blog') }}@endsection
@section('bodyClass')kv-page kv-cms @endsection
@section('description'){{ __('cms.blog_intro', [ 'organisation' => $organisation->name ]) }}@endsection

@push('head')
    @include('cms.partials.alternates', [ 'alternates' => $alternates ])
@endpush

@section('content')

    <div class="kv-pagehead {{ $organisation->getColourPalette()->getClassFor(0) }}">
        <div class="kv-wrap">
            <h1 class="kv-pagehead-title">{{ __('cms.blog') }}</h1>
            <div class="kv-pagehead-lead"><p>{{ __('cms.blog_intro', [ 'organisation' => $organisation->name ]) }}</p></div>
        </div>
    </div>

    <section class="kv-sec kv-cms-blog">
        <div class="kv-wrap">
            @if($posts->count() === 0)
                <p class="kv-cms-empty">{{ __('cms.no_posts') }}</p>
            @else
                <div class="kv-posts">
                    @foreach($posts as $translation)
                        @include('cms.blog._card', [
                            'post' => app(\App\Cms\Blog::class)->summary($translation, $organisation),
                            'locale' => $translation->locale,
                            'headingLevel' => 2,
                            'fieldClass' => $organisation->getColourPalette()->getClassFor($translation->post_id),
                        ])
                    @endforeach
                </div>

                @if($posts->hasPages())
                    <nav class="kv-pager" aria-label="{{ __('cms.blog') }}">
                        @if(!$posts->onFirstPage())
                            <a class="kv-pager-prev" href="{{ $posts->currentPage() === 2 ? \App\Cms\Blog::indexUrl(app()->getLocale()) : $posts->previousPageUrl() }}" rel="prev">&larr; {{ __('cms.newer_posts') }}</a>
                        @endif
                        @if($posts->hasMorePages())
                            <a class="kv-pager-next" href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('cms.older_posts') }} &rarr;</a>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </section>

@endsection
