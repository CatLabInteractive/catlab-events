@extends('layouts/admin')

@section('content')

    <h2>Pagina's</h2>

    <p>
        De pagina's van de website van {{ $organisation->name }}. Elke pagina bestaat in een of meer talen;
        een groen label is gepubliceerd, een grijs label is een concept, met <strong>+</strong> voeg je een vertaling toe.
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
        <a href="{{ action('Admin\PageController@create') }}" class="btn btn-success">Nieuwe pagina</a>
    </p>

    @if(count($rows) === 0)
        <p class="alert alert-info">Er zijn nog geen pagina's.</p>
    @else
        <table class="table cms-page-tree">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Adres</th>
                    <th>Talen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                @php
                    $page = $row['page'];
                    $main = $page->translation($defaultLocale) ?: $page->translations->first();
                    $isHome = (int) $organisation->home_page_id === (int) $page->id;
                @endphp
                <tr @if($isHome) data-home-page="{{ $page->id }}" @endif>
                    <td>
                        <span style="padding-left: {{ $row['depth'] * 1.5 }}em;">
                            @if($row['depth'] > 0)&#8627;@endif
                            @if($main)
                                <a href="{{ action('Admin\PageController@edit', [ $page->id, $main->locale ]) }}">{{ $row['title'] }}</a>
                            @else
                                {{ $row['title'] }}
                            @endif
                        </span>
                        @if($isHome)
                            <span class="badge badge-primary">Startpagina</span>
                        @endif
                        @if($page->show_in_menu)
                            <span class="badge badge-light">in menu</span>
                        @endif
                    </td>
                    <td>
                        @if($main)
                            <code>/{{ $main->locale === $defaultLocale ? '' : $main->locale . '/' }}{{ $main->path }}</code>
                        @endif
                    </td>
                    <td>
                        @foreach($locales as $locale)
                            @php($translation = $page->translation($locale))
                            @if($translation)
                                <a href="{{ action('Admin\PageController@edit', [ $page->id, $locale ]) }}"
                                   class="badge {{ $translation->is_published ? 'badge-success' : 'badge-secondary' }}"
                                   title="{{ $translation->is_published ? 'Gepubliceerd' : 'Concept' }}"
                                   data-locale-badge="{{ $locale }}" data-state="{{ $translation->is_published ? 'published' : 'draft' }}">{{ $locale }}</a>
                            @else
                                <a href="{{ action('Admin\PageController@createTranslation', [ $page->id, $locale ]) }}"
                                   class="badge badge-light" title="Vertaling toevoegen"
                                   data-locale-badge="{{ $locale }}" data-state="missing">+ {{ $locale }}</a>
                            @endif
                        @endforeach
                    </td>
                    <td class="text-right text-nowrap">
                        @if($main)
                            <a href="{{ $main->getUrl() }}?preview=1" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Bekijk</a>
                            <a href="{{ action('Admin\PageController@edit', [ $page->id, $main->locale ]) }}" class="btn btn-sm btn-outline-primary">Bewerk</a>
                        @endif

                        <form action="{{ action('Admin\PageController@setHome', [ $page->id ]) }}" method="post" class="d-inline">
                            {{ csrf_field() }}
                            @if($isHome)
                                <input type="hidden" name="clear" value="1" />
                                <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        onclick="return confirm('Deze pagina niet langer als startpagina gebruiken? / toont dan weer de kalender.');">Geen startpagina meer</button>
                            @else
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Stel in als startpagina</button>
                            @endif
                        </form>

                        @if(!$isHome)
                            <form action="{{ action('Admin\PageController@destroy', [ $page->id ]) }}" method="post" class="d-inline"
                                  onsubmit="return confirm('Deze pagina en al haar vertalingen verwijderen?');">
                                {{ csrf_field() }}
                                {{ method_field('DELETE') }}
                                <button type="submit" class="btn btn-sm btn-outline-danger">Verwijder</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
