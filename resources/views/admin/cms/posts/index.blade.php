@extends('layouts/admin')

@section('content')

    <h2>Blogberichten</h2>

    <p>
        De blogberichten van {{ $organisation->name }}, nieuwste eerst. Een bericht verschijnt op de website
        vanaf zijn publicatiedatum, in elke taal waarin het gepubliceerd is: een groen label is gepubliceerd,
        een grijs label is een concept, met <strong>+</strong> voeg je een vertaling toe.
    </p>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p>
        <a href="{{ action('Admin\PostController@create') }}" class="btn btn-success">Nieuw bericht</a>
    </p>

    @if(count($posts) === 0)
        <p class="alert alert-info">Er zijn nog geen blogberichten.</p>
    @else
        <table class="table cms-post-list">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Publicatiedatum</th>
                    <th>Talen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($posts as $post)
                @php
                    $main = $post->translation($defaultLocale) ?: $post->translations->first();
                    $isFuture = $post->published_at && $post->published_at->isFuture();
                @endphp
                <tr>
                    <td>
                        @if($main)
                            <a href="{{ action('Admin\PostController@edit', [ $post->id, $main->locale ]) }}">{{ $main->title }}</a>
                        @else
                            Bericht #{{ $post->id }} (zonder vertaling)
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($post->published_at)
                            {{ $post->published_at->copy()->locale('nl')->translatedFormat('j F Y H:i') }}
                            @if($isFuture)
                                <span class="badge badge-info">gepland</span>
                            @endif
                        @else
                            <span class="text-muted">geen datum (niet zichtbaar)</span>
                        @endif
                    </td>
                    <td>
                        @foreach($locales as $locale)
                            @php($translation = $post->translation($locale))
                            @if($translation)
                                <a href="{{ action('Admin\PostController@edit', [ $post->id, $locale ]) }}"
                                   class="badge {{ $translation->is_published ? 'badge-success' : 'badge-secondary' }}"
                                   title="{{ $translation->is_published ? 'Gepubliceerd' : 'Concept' }}"
                                   data-locale-badge="{{ $locale }}" data-state="{{ $translation->is_published ? 'published' : 'draft' }}">{{ $locale }}</a>
                            @else
                                <a href="{{ action('Admin\PostController@createTranslation', [ $post->id, $locale ]) }}"
                                   class="badge badge-light" title="Vertaling toevoegen"
                                   data-locale-badge="{{ $locale }}" data-state="missing">+ {{ $locale }}</a>
                            @endif
                        @endforeach
                    </td>
                    <td class="text-right text-nowrap">
                        @if($main)
                            <a href="{{ $main->getUrl() }}?preview=1" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Bekijk</a>
                            <a href="{{ action('Admin\PostController@edit', [ $post->id, $main->locale ]) }}" class="btn btn-sm btn-outline-primary">Bewerk</a>
                        @endif

                        <form action="{{ action('Admin\PostController@destroy', [ $post->id ]) }}" method="post" class="d-inline"
                              onsubmit="return confirm('Dit bericht en al zijn vertalingen verwijderen?');">
                            {{ csrf_field() }}
                            {{ method_field('DELETE') }}
                            <button type="submit" class="btn btn-sm btn-outline-danger">Verwijder</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
