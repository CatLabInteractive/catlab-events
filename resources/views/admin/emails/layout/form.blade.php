{{-- The Charon form of the mail layout, under the tabs of the E-mails settings. --}}
@extends('charonfrontend::crud.form')

@section('cfcontent')

    @include('admin.emails.partials.tabs', [ 'active' => 'layout' ])

    <p>Elke mail gaat in dezelfde layout de deur uit. Kies hier de accentkleur en de voettekst, of vervang de hele layout.</p>

    @parent

@endsection
