{{--
    charon-frontend's crud/form, with two changes: the form is multipart, and
    relationships to an Asset are rendered as a file field (see
    BaseAdminController::createFormView). Every other field still comes from
    charon-frontend's own form-fields template.
--}}
@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8" enctype="multipart/form-data">
        @csrf
        @method($method)

        @include('charonfrontend::crud.form-fields')

        @foreach($assetFields as $assetField)
            @include('admin.crud.asset-field', [ 'assetField' => $assetField ])
        @endforeach

        <input type="hidden" name="assetFields" value="{{ implode(',', array_column($assetFields, 'name')) }}">

        <div class="form-group">
            <input type="submit" value="{{ ucfirst($verb) }}" class="btn btn-primary">
        </div>

    </form>

@endsection
