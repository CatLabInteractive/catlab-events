@extends('layouts/admin')

@section('content')

    <h2>Sjabloon aanpassen</h2>

    @include('admin.emails.partials.tabs', [ 'active' => 'library' ])

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @section('extraFields')
        <p>Mail: <strong>{{ $label }}</strong></p>

        <div class="form-group">
            <label for="name">Naam</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name', $template->name) }}" maxlength="100" required />
        </div>
    @endsection

    @include('admin.emails.partials.editor', [
        'formAction' => action('Admin\EmailLibraryController@update', [ $template->id ]),
        'previewAction' => action('Admin\EmailLibraryController@preview', [ $template->id ]),
        'cancelUrl' => action('Admin\EmailLibraryController@index')
    ])

@endsection
