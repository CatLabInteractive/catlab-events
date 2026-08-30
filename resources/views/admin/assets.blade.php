@extends('layouts/admin')

@section('title')
    Upload assets
@endsection

@section('content')

    <h2>File upload</h2>
    <form method="POST" action="{{ action('Admin\AssetController@upload') }}" accept-charset="UTF-8" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" id="file">
        <input type="submit" value="Upload">
    </form>

@endsection