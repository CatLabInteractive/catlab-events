{{-- The Charon form of a mail template, under the tabs of the E-mails settings. --}}
@extends('charonfrontend::crud.form')

@section('cfcontent')

    @include('admin.emails.partials.tabs', [ 'active' => 'library' ])

    @parent

@endsection
