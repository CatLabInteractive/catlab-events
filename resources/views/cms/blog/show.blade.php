@extends('layouts/home')

@section('title'){{ $pageTitle }}@endsection
@section('bodyClass')kv-page kv-cms @endsection

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

    <article class="kv-cms-post kv-cms-post-{{ $post->id }}" lang="{{ $translation->locale }}">

        <div class="kv-pagehead kv-pagehead-post {{ organisation()->getColourPalette()->getClassFor($post->id) }}">
            <div class="kv-wrap">
                <p class="kv-cms-back"><a href="{{ \App\Cms\Blog::indexUrl($translation->locale) }}">&larr; {{ __('cms.back_to_blog') }}</a></p>
                <h1 class="kv-pagehead-title kv-cms-post-title">{{ $translation->title }}</h1>
                <p class="kv-cms-post-meta">
                    @if($publishedAt)
                        <span class="sr-only">{{ __('cms.published_on') }}</span>
                        <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->copy()->locale($translation->locale)->translatedFormat('j F Y') }}</time>
                        &middot;
                    @endif
                    <span class="kv-cms-post-author">{{ $byline }}</span>
                </p>
            </div>
        </div>

        <section class="kv-sec kv-cms-post-body">
            <div class="kv-wrap kv-wrap-prose">
                @if($image)
                    <figure class="kv-cms-post-image">
                        @include('blocks.image', [ 'image' => $image, 'sizes' => '(min-width: 768px) 720px, 100vw', 'alt' => $translation->title, 'priority' => true ])
                    </figure>
                @endif

                <div class="kv-prose">
                    {!! $translation->body !!}
                </div>

                @if(count($otherTranslations) > 0)
                    <p class="kv-cms-translations">
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
