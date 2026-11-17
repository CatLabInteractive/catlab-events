@extends('layouts/admin')

@section('content')

    <h2>Nieuw sjabloon</h2>

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
        <div class="form-group">
            <label for="type">Mail</label>
            <select class="form-control" id="type" name="type"
                    onchange="window.location.href = '{{ action('Admin\EmailLibraryController@create') }}?type=' + this.value;">
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" @if(old('type', $type) === $key) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted">Een andere mail kiezen laadt de standaardtekst van die mail.</small>
        </div>

        <div class="form-group">
            <label for="name">Naam</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name') }}" maxlength="100" required />
        </div>
    @endsection

    @include('admin.emails.partials.editor', [
        'formAction' => action('Admin\EmailLibraryController@store'),
        'previewAction' => action('Admin\EmailLibraryController@previewNew'),
        'cancelUrl' => action('Admin\EmailLibraryController@index')
    ])

@endsection
