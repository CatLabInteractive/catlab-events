@extends('layouts/home')

@section('title'){{ __('cms.blog') }}@endsection
@section('description'){{ __('cms.blog_intro', [ 'organisation' => $organisation->name ]) }}@endsection

@push('head')
    @include('cms.partials.alternates', [ 'alternates' => $alternates ])
@endpush

@section('content')

    <div class="cms-page cms-blog">

        <section class="cms-page-header">
            <div class="container">
                <h1 class="cms-page-title">{{ __('cms.blog') }}</h1>
            </div>
        </section>

        <section class="cms-block">
            <div class="container">
                @if($posts->count() === 0)
                    <p class="cms-blog-empty">{{ __('cms.no_posts') }}</p>
                @else
                    <div class="row cms-posts">
                        @foreach($posts as $translation)
                            <div class="col-md-6 col-lg-4 mb-4">
                                @include('cms.blog._card', [
                                    'post' => app(\App\Cms\Blog::class)->summary($translation, $organisation),
                                    'locale' => $translation->locale,
                                    'headingLevel' => 2,
                                ])
                            </div>
                        @endforeach
                    </div>

                    @if($posts->hasPages())
                        <nav class="cms-pagination d-flex justify-content-between" aria-label="{{ __('cms.blog') }}">
                            <span>
                                @if(!$posts->onFirstPage())
                                    <a href="{{ $posts->currentPage() === 2 ? \App\Cms\Blog::indexUrl(app()->getLocale()) : $posts->previousPageUrl() }}" rel="prev">&larr; {{ __('cms.newer_posts') }}</a>
                                @endif
                            </span>
                            <span>
                                @if($posts->hasMorePages())
                                    <a href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('cms.older_posts') }} &rarr;</a>
                                @endif
                            </span>
                        </nav>
                    @endif
                @endif
            </div>
        </section>

    </div>

@endsection
