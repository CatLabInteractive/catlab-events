@extends('layouts/admin')

@section('content')

    <h2>{{ $label }}</h2>
    <h3>Aanpassen</h3>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$customised)
        <p class="alert alert-info">
            Je vertrekt van de standaardversie. Pas je niets aan, dan blijft de standaardversie verstuurd worden.
        </p>
    @endif

    @include('admin.emails.partials.editor', [
        'formAction' => action('Admin\EmailTemplateController@update', [ $type ]),
        'previewAction' => action('Admin\EmailTemplateController@preview', [ $type ]),
        'cancelUrl' => action('Admin\EmailTemplateController@show', [ $type ])
    ])

    @if($customised)
        <form action="{{ action('Admin\EmailTemplateController@reset', [ $type ]) }}" method="post"
              style="margin-top: 15px;"
              onsubmit="return confirm('De aangepaste versie wordt verwijderd en de standaardversie wordt weer verstuurd. Doorgaan?');">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-danger">Terug naar standaard</button>
        </form>
    @endif

@endsection
