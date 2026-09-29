@extends('layouts/admin')

@php
    $localeNames = [ 'nl' => 'Nederlands', 'en' => 'Engels', 'fr' => 'Frans' ];
    $mainTranslation = $translations->get($defaultLocale) ?: $translations->first();
    $formAction = $post->exists
        ? action('Admin\PostController@update', [ $post->id, $locale ])
        : action('Admin\PostController@store');
    $datePrefix = $post->published_at
        ? $post->published_at->copy()->setTimezone(\App\Models\PostTranslation::URL_TIMEZONE)->format('Y/m/d')
        : 'jjjj/mm/dd';
@endphp

@push('scripts')
    <script src="{{ asset('js/tinymce/tinymce.min.js') }}"></script>
@endpush

@section('content')

    <p><a href="{{ action('Admin\PostController@index') }}">&larr; Alle blogberichten</a></p>

    <h2>
        @if($post->exists)
            {{ $mainTranslation ? $mainTranslation->title : 'Bericht #' . $post->id }}
        @else
            Nieuw bericht
        @endif
    </h2>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <p class="mb-1">Het bericht is niet opgeslagen:</p>
            <ul class="mb-0">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($post->exists)
        <ul class="nav nav-tabs mb-3">
            @foreach($locales as $tabLocale)
                @php($tabTranslation = $translations->get($tabLocale))
                <li class="nav-item">
                    @if($tabTranslation)
                        <a class="nav-link {{ $tabLocale === $locale ? 'active' : '' }}"
                           href="{{ action('Admin\PostController@edit', [ $post->id, $tabLocale ]) }}">
                            {{ $localeNames[$tabLocale] ?? $tabLocale }}
                            <span class="badge {{ $tabTranslation->is_published ? 'badge-success' : 'badge-secondary' }}">{{ $tabTranslation->is_published ? 'gepubliceerd' : 'concept' }}</span>
                        </a>
                    @else
                        <a class="nav-link {{ $tabLocale === $locale ? 'active' : 'text-muted' }}"
                           href="{{ action('Admin\PostController@createTranslation', [ $post->id, $tabLocale ]) }}">
                            + {{ $localeNames[$tabLocale] ?? $tabLocale }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <form action="{{ $formAction }}" method="post" class="cms-editor" data-cms-editor
          data-cms-upload-url="{{ action('Admin\CmsAssetController@upload') }}"
          data-cms-assets-url="{{ action('Admin\CmsAssetController@index') }}">
        {{ csrf_field() }}
        @if($post->exists)
            {{ method_field('PUT') }}
        @endif

        <div class="row">
            <div class="col-lg-8">
                <h4>{{ $localeNames[$locale] ?? $locale }}</h4>

                @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => 'title', 'value' => old('title', $translation->title), 'error' => 'title', 'max' => 255, 'required' => true ] ])

                <div class="form-group">
                    <label class="mb-1">Adres <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text">{{ url($locale === $defaultLocale ? '/' : '/' . $locale) }}/{{ $datePrefix }}/</span>
                        </div>
                        <input type="text" name="slug" class="form-control{{ $errors->has('slug') ? ' is-invalid' : '' }}" value="{{ old('slug', $translation->slug) }}" maxlength="191" required pattern="[a-z0-9\-]+" />
                    </div>
                    @if($errors->has('slug'))
                        <div class="invalid-feedback d-block">{{ $errors->first('slug') }}</div>
                    @endif
                    <small class="form-text text-muted">Kleine letters, cijfers en koppeltekens, bv. <code>quiz-van-het-jaar</code>. De datum komt van de publicatiedatum.</small>
                </div>

                @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Auteur', 'name' => 'author', 'value' => old('author', $translation->author), 'error' => 'author', 'max' => 191, 'help' => 'Leeg: de naam van de organisatie (' . $organisation->name . ').' ] ])
                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Samenvatting', 'name' => 'excerpt', 'value' => old('excerpt', $translation->excerpt), 'error' => 'excerpt', 'max' => 1000, 'rows' => 2, 'help' => 'Gewone tekst, getoond in de lijst van berichten.' ] ])
                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'html', 'label' => 'Tekst', 'name' => 'body', 'value' => old('body', $translation->body), 'error' => 'body', 'rows' => 16 ] ])

                <div class="form-group">
                    <div class="form-check">
                        <input type="hidden" name="is_published" value="0" />
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" name="is_published" value="1" @if(old('is_published', $translation->is_published)) checked @endif />
                            Gepubliceerd in het {{ $localeNames[$locale] ?? $locale }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <h4>Bericht</h4>
                <p class="text-muted small">Deze instellingen gelden voor alle talen.</p>

                <div class="form-group">
                    <label class="mb-1" for="cms-post-published-at">Publicatiedatum</label>
                    <input type="datetime-local" id="cms-post-published-at" name="published_at"
                           class="form-control form-control-sm{{ $errors->has('published_at') ? ' is-invalid' : '' }}"
                           value="{{ old('published_at', $publishedAtValue) }}" />
                    @if($errors->has('published_at'))
                        <div class="invalid-feedback d-block">{{ $errors->first('published_at') }}</div>
                    @endif
                    <small class="form-text text-muted">Bepaalt het adres (/jjjj/mm/dd/…) en de volgorde. Een datum in de toekomst plant het bericht in; zonder datum is het nergens zichtbaar.</small>
                </div>

                @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Uitgelichte afbeelding', 'name' => 'featured_image_id', 'value' => old('featured_image_id', $post->featured_image_id), 'error' => 'featured_image_id' ] ])
            </div>
        </div>

        <h4>Zoekmachines en delen</h4>
        <div class="row">
            <div class="col-lg-8">
                @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'SEO-titel', 'name' => 'meta_title', 'value' => old('meta_title', $translation->meta_title), 'error' => 'meta_title', 'max' => 255, 'help' => 'Leeg: de titel van het bericht.' ] ])
                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'SEO-beschrijving', 'name' => 'meta_description', 'value' => old('meta_description', $translation->meta_description), 'error' => 'meta_description', 'max' => 320, 'rows' => 2, 'help' => 'Leeg: de samenvatting.' ] ])
            </div>
        </div>

        <p class="mt-3">
            <button type="submit" class="btn btn-success">Opslaan</button>
            <button type="submit" class="btn btn-outline-secondary" name="preview" value="1">Opslaan en bekijken</button>
        </p>
    </form>

    @include('admin.cms._image_picker')

    @if($post->exists)
        <hr />
        <div class="d-flex flex-wrap">
            @if($translation->exists)
                <form action="{{ action('Admin\PostController@destroyTranslation', [ $post->id, $locale ]) }}" method="post" class="mr-2 mb-2"
                      onsubmit="return confirm('De {{ $localeNames[$locale] ?? $locale }}e versie van dit bericht verwijderen?');">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn btn-sm btn-outline-danger">Verwijder deze vertaling</button>
                </form>
            @endif

            <form action="{{ action('Admin\PostController@destroy', [ $post->id ]) }}" method="post" class="mb-2"
                  onsubmit="return confirm('Dit bericht en al zijn vertalingen verwijderen?');">
                {{ csrf_field() }}
                {{ method_field('DELETE') }}
                <button type="submit" class="btn btn-sm btn-danger">Verwijder het bericht</button>
            </form>
        </div>
    @endif

@endsection
