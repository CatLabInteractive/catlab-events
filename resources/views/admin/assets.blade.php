@extends('layouts/admin')

@section('title')
    Upload assets
@endsection

@section('content')

    <h2>File upload</h2>
    {{ Form::open([ 'url' => action('Admin\AssetController@upload'), 'files' => true ]) }}
    {{ Form::file('file') }}
    {{ Form::submit('Upload') }}
    {{ Form::close() }}

@endsection