{{-- The Charon index of the pages, with the offer to manage the fixed parts of the menu. --}}
@extends('charonfrontend::crud.index')

@section('cfcontent')

    @if(count($unmanagedLinks ?? []) > 0)
        <div class="alert alert-light border">
            <p class="mb-2">
                Deze vaste onderdelen staan nu op een vaste plaats in het menu
                ({{ implode(', ', array_map(function ($link) { return \App\Models\Page::FIXED_LINK_TITLES[$link]['nl']; }, $unmanagedLinks)) }}).
                Voeg ze toe als menulink om ze te verplaatsen, te hernoemen of te verbergen.
            </p>
            <form method="POST" action="{{ action('Admin\PageController@addFixedLinks') }}" class="mb-0">
                {{ csrf_field() }}
                <button type="submit" class="btn btn-sm btn-outline-primary">Vaste onderdelen als menulinks toevoegen</button>
            </form>
        </div>
    @endif

    @parent

@endsection
