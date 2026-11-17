@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>{{ $label }}: eigen tekst</h3>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="alert alert-info">
        @if($isOwnText)
            Dit evenement verstuurt deze eigen tekst. Wijzigingen gelden alleen voor dit evenement.
        @else
            Dit evenement heeft nog geen eigen tekst voor deze mail. De tekst hieronder is de versie die het nu verstuurt.
            Wanneer je opslaat, krijgt dit evenement een eigen tekst. De standaard van de organisatie verandert niet.
        @endif
    </p>

    @include('admin.emails.partials.editor', [
        'formAction' => action('Admin\EventEmailController@update', [ $event->id, $type ]),
        'previewAction' => action('Admin\EventEmailController@preview', [ $event->id, $type ]),
        'previewCaption' => 'Met de gegevens van dit evenement en een verzonnen speler.',
        'cancelUrl' => action('Admin\EventEmailController@index', [ $event->id ])
    ])

@endsection
