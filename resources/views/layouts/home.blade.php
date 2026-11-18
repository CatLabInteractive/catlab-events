{{-- Full-width pages that build all their own sections (series and organisation pages). --}}
@extends('layouts.base')

@section('page')
    @include('layouts.blocks.flash')
    @yield('content')
    @yield('second-content')
@endsection
