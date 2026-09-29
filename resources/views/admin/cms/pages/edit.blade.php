@extends('layouts/admin')

@php
    $localeNames = [ 'nl' => 'Nederlands', 'en' => 'Engels', 'fr' => 'Frans' ];
    $mainTranslation = $translations->get($defaultLocale) ?: $translations->first();
    $isNewTranslation = !$translation->exists;
    $formAction = $page->exists
        ? action('Admin\PageController@update', [ $page->id, $locale ])
        : action('Admin\PageController@store');
@endphp

@push('scripts')
    <script src="{{ asset('js/tinymce/tinymce.min.js') }}"></script>
@endpush

@section('content')

    <p><a href="{{ action('Admin\PageController@index') }}">&larr; Alle pagina's</a></p>

    <h2>
        @if($page->exists)
            {{ $mainTranslation ? $mainTranslation->title : 'Pagina #' . $page->id }}
        @else
            Nieuwe pagina
        @endif
        @if($isHome)
            <span class="badge badge-primary">Startpagina</span>
        @endif
    </h2>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <p class="mb-1">De pagina is niet opgeslagen:</p>
            <ul class="mb-0">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($page->exists)
        <ul class="nav nav-tabs mb-3">
            @foreach($locales as $tabLocale)
                @php($tabTranslation = $translations->get($tabLocale))
                <li class="nav-item">
                    @if($tabTranslation)
                        <a class="nav-link {{ $tabLocale === $locale ? 'active' : '' }}"
                           href="{{ action('Admin\PageController@edit', [ $page->id, $tabLocale ]) }}">
                            {{ $localeNames[$tabLocale] ?? $tabLocale }}
                            <span class="badge {{ $tabTranslation->is_published ? 'badge-success' : 'badge-secondary' }}">{{ $tabTranslation->is_published ? 'gepubliceerd' : 'concept' }}</span>
                        </a>
                    @else
                        <a class="nav-link {{ $tabLocale === $locale ? 'active' : 'text-muted' }}"
                           href="{{ action('Admin\PageController@createTranslation', [ $page->id, $tabLocale ]) }}">
                            + {{ $localeNames[$tabLocale] ?? $tabLocale }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($isNewTranslation && count($translations) > 0)
            <form action="{{ action('Admin\PageController@copyTranslation', [ $page->id, $locale ]) }}" method="post" class="form-inline mb-3 p-2 bg-light border rounded">
                {{ csrf_field() }}
                <span class="mr-2">Deze pagina bestaat nog niet in het {{ $localeNames[$locale] ?? $locale }}. Begin met een kopie? Kopieer uit</span>
                <select name="from" class="form-control form-control-sm mr-2">
                    @foreach($translations as $sourceLocale => $sourceTranslation)
                        <option value="{{ $sourceLocale }}">{{ $localeNames[$sourceLocale] ?? $sourceLocale }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Kopiëren</button>
            </form>
        @endif
    @endif

    <form action="{{ $formAction }}" method="post" class="cms-editor" data-cms-editor
          data-cms-upload-url="{{ action('Admin\CmsAssetController@upload') }}"
          data-cms-assets-url="{{ action('Admin\CmsAssetController@index') }}">
        {{ csrf_field() }}
        @if($page->exists)
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
                            <span class="input-group-text">{{ url($locale === $defaultLocale ? '/' : '/' . $locale) }}/{{ $page->parent && $page->parent->translation($locale) ? $page->parent->translation($locale)->path . '/' : '' }}</span>
                        </div>
                        <input type="text" name="slug" class="form-control{{ $errors->has('slug') ? ' is-invalid' : '' }}" value="{{ old('slug', $translation->slug) }}" maxlength="191" required pattern="[a-z0-9\-]+" />
                    </div>
                    @if($errors->has('slug'))
                        <div class="invalid-feedback d-block">{{ $errors->first('slug') }}</div>
                    @endif
                    <small class="form-text text-muted">Kleine letters, cijfers en koppeltekens, bv. <code>over-ons</code>.</small>
                </div>

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
                <h4>Pagina</h4>
                <p class="text-muted small">Deze instellingen gelden voor alle talen.</p>

                <div class="form-group">
                    <label class="mb-1">Bovenliggende pagina</label>
                    <select name="parent_id" class="form-control form-control-sm{{ $errors->has('parent_id') ? ' is-invalid' : '' }}">
                        <option value="">(geen: hoofdniveau)</option>
                        @foreach($parentOptions as $option)
                            <option value="{{ $option['page']->id }}" @if((string) old('parent_id', $page->parent_id) === (string) $option['page']->id) selected @endif>
                                {{ str_repeat('— ', $option['depth']) }}{{ $option['title'] }}
                            </option>
                        @endforeach
                    </select>
                    @if($errors->has('parent_id'))
                        <div class="invalid-feedback d-block">{{ $errors->first('parent_id') }}</div>
                    @endif
                </div>

                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'number', 'label' => 'Volgorde', 'name' => 'sort_order', 'value' => old('sort_order', $page->sort_order ?? 0), 'error' => 'sort_order', 'help' => 'Lager staat eerst, in het menu en in deze lijst.' ] ])
                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'checkbox', 'label' => 'Toon in het menu', 'name' => 'show_in_menu', 'value' => old('show_in_menu', $page->show_in_menu), 'error' => 'show_in_menu' ] ])
            </div>
        </div>

        <h4 class="mt-3">Secties</h4>

        <div class="cms-blocks" data-cms-blocks>
            @foreach($blocks as $i => $block)
                @php($blockType = is_string($block['type'] ?? null) ? ($blockTypes[$block['type']] ?? null) : null)
                @if($blockType)
                    @include('admin.cms.blocks._block', [ 'i' => $i, 'block' => $block, 'blockType' => $blockType ])
                @endif
            @endforeach
        </div>

        <div class="form-inline mb-4 p-2 bg-light border rounded">
            <label class="mr-2" for="cms-add-block-type">Sectie toevoegen:</label>
            <select id="cms-add-block-type" class="form-control form-control-sm mr-2" data-cms-block-select>
                @foreach($blockTypes as $typeKey => $blockType)
                    <option value="{{ $typeKey }}">{{ $blockType->label() }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-primary" data-cms-action="add-block">Toevoegen</button>
        </div>

        @foreach($blockTypes as $typeKey => $blockType)
            <template data-block-template="{{ $typeKey }}" data-block-type="{{ $typeKey }}">
                @include('admin.cms.blocks._block', [
                    'i' => '__INDEX__',
                    'block' => [ 'id' => '__ID__', 'type' => $typeKey, 'data' => $blockType->defaults() ],
                    'blockType' => $blockType,
                ])
            </template>
        @endforeach

        <h4>Zoekmachines en delen</h4>
        <div class="row">
            <div class="col-lg-8">
                @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'SEO-titel', 'name' => 'meta_title', 'value' => old('meta_title', $translation->meta_title), 'error' => 'meta_title', 'max' => 255, 'help' => 'Leeg: de titel van de pagina.' ] ])
                @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'SEO-beschrijving', 'name' => 'meta_description', 'value' => old('meta_description', $translation->meta_description), 'error' => 'meta_description', 'max' => 320, 'rows' => 2, 'help' => 'Leeg: het begin van de eerste tekst.' ] ])
            </div>
            <div class="col-lg-4">
                @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Deelafbeelding', 'name' => 'og_image_id', 'value' => old('og_image_id', $translation->og_image_id), 'error' => 'og_image_id' ] ])
            </div>
        </div>

        <p class="mt-3">
            <button type="submit" class="btn btn-success">Opslaan</button>
            <button type="submit" class="btn btn-outline-secondary" name="preview" value="1">Opslaan en bekijken</button>
        </p>
    </form>

    @include('admin.cms._image_picker')

    @if($page->exists)
        <hr />
        <div class="d-flex flex-wrap">
            @if($translation->exists)
                <form action="{{ action('Admin\PageController@destroyTranslation', [ $page->id, $locale ]) }}" method="post" class="mr-2 mb-2"
                      onsubmit="return confirm('De {{ $localeNames[$locale] ?? $locale }}e versie van deze pagina verwijderen?');">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn btn-sm btn-outline-danger">Verwijder deze vertaling</button>
                </form>
            @endif

            @if(!$isHome)
                <form action="{{ action('Admin\PageController@destroy', [ $page->id ]) }}" method="post" class="mb-2"
                      onsubmit="return confirm('Deze pagina en al haar vertalingen verwijderen?');">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn btn-sm btn-danger">Verwijder de pagina</button>
                </form>
            @endif
        </div>
    @endif

@endsection
