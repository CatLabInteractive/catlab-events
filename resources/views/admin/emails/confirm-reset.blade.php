{{-- Confirm going back to the standard version of a mail (a Charon destroy); $question. --}}
@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8">
        @csrf
        @method('DELETE')
        @if($return)
            <input type="hidden" name="{{ $returnParameter }}" value="{{ $return }}">
        @endif

        <p>{{ $question }}</p>

        <input type="submit" value="Terug naar standaard" class="btn btn-danger">
        <a href="{{ $back }}" class="btn btn-secondary">{{ __('charonfrontend::crud.no') }}</a>
    </form>

@endsection
