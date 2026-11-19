{{-- The Charon index of the automatic mails, under the tabs of the E-mails settings. --}}
@extends('charonfrontend::crud.index')

@section('cfcontent')

    @include('admin.emails.partials.tabs', [ 'active' => 'standard' ])

    <p>
        Deze mails worden automatisch verstuurd. Bekijk hoe ze eruit zien, of pas ze aan voor alle
        evenementen van je organisatie.
    </p>

    @parent

@endsection
