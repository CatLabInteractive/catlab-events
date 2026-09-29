@extends('emails/layouts/layout')

@section('content')
    {{-- Written by an admin in the panel, e.g. an edited waiting list invitation. --}}
    {!! $content !!}
@endsection
