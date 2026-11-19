{{-- The Charon index of the mail templates, under the tabs of the E-mails settings. --}}
@extends('charonfrontend::crud.index')

@section('cfcontent')

    @include('admin.emails.partials.tabs', [ 'active' => 'library' ])

    <p>
        Een sjabloon is een mail met een naam die je bij meerdere evenementen kan kiezen.
    </p>

    @parent

@endsection
