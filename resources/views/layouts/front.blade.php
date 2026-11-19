{{--
    A page with a coloured page head (or a 'banner' section of its own) above
    the content. Pages that build their own kv-sec sections set the body class
    kv-page; any other content is put in one plain section that styles basic
    HTML (headings, tables, forms, buttons) in the Kleurveld look.
--}}
@extends('layouts.base')

@section('page')
    @hasSection('banner')
        @yield('banner')
    @else
        <?php $pageHeadClass = (isset($series) && $series instanceof \App\Models\Series) ? $series->getColourClass() : 'kv-field-0'; ?>
        <div class="kv-pagehead {{ $pageHeadClass }}">
            <div class="kv-wrap">
                <h1 class="kv-pagehead-title">@yield('title')</h1>
            </div>
        </div>
    @endif

    @include('layouts.blocks.flash')

    @if(str_contains($__env->yieldContent('bodyClass'), 'kv-page'))
        @yield('content')
        @yield('second-content')
    @else
        <div class="kv-plain">
            <div class="kv-wrap kv-content">
                @yield('content')
                @yield('second-content')
            </div>
        </div>
    @endif
@endsection
