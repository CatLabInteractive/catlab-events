@extends('layouts/home')

@section('title'){{ $pageTitle }}@endsection

@if($description)
    @section('description'){{ $description }}@endsection
@endif

@push('head')
    @include('cms.partials.head')
    @include('cms.partials.alternates', [ 'alternates' => $alternates ])
    @if($publishedAt)
        <meta property="article:published_time" content="{{ $publishedAt->toIso8601String() }}" />
    @endif
@endpush

@section('jsonld-content')
    @include('cms.blog._jsonld')
@endsection

@section('content')

    <article class="cms-page cms-post cms-post-{{ $post->id }}" lang="{{ $translation->locale }}">

        <section class="cms-page-header">
            <div class="container">
                <p class="cms-post-back"><a href="{{ \App\Cms\Blog::indexUrl($translation->locale) }}">&larr; {{ __('cms.back_to_blog') }}</a></p>
                <h1 class="cms-post-title">{{ $translation->title }}</h1>
                <p class="cms-post-meta">
                    @if($publishedAt)
                        {{ __('cms.published_on') }}
                        <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->copy()->locale($translation->locale)->translatedFormat('j F Y') }}</time>
                        &middot;
                    @endif
                    <span class="cms-post-author">{{ $byline }}</span>
                </p>
            </div>
        </section>

        <section class="cms-block">
            <div class="container">
                @if($imageUrl)
                    <figure class="cms-post-image">
                        <img class="img-fluid" src="{{ $imageUrl }}" alt="{{ $translation->title }}" />
                    </figure>
                @endif

                <div class="cms-prose">
                    {!! $translation->body !!}
                </div>

                @if(count($otherTranslations) > 0)
                    <p class="cms-post-translations">
                        {{ __('cms.in_other_languages') }}:
                        @foreach($otherTranslations as $otherLocale => $otherUrl)
                            <a href="{{ $otherUrl }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}">{{ __('cms.languages.' . $otherLocale) }}</a>@if(!$loop->last), @endif
                        @endforeach
                    </p>
                @endif
            </div>
        </section>

    </article>

@endsection
