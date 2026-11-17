@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>{{ $label }}</h3>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php($chosen = old('source', $source))

    @section('extraFields')
        <div class="form-group">
            <label>Welke mail krijgt dit evenement?</label>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="source" id="source-organisation"
                       value="organisation" @if($chosen === 'organisation') checked @endif />
                <label class="form-check-label" for="source-organisation">Standaard van de organisatie</label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="source" id="source-template"
                       value="template" @if($chosen === 'template') checked @endif
                       @if(count($libraryTemplates) === 0) disabled @endif />
                <label class="form-check-label" for="source-template">Sjabloon</label>
                @if(count($libraryTemplates) === 0)
                    <small class="text-muted">Nog geen sjablonen voor deze mail</small>
                @else
                    <select class="form-control" name="email_template_id" id="email_template_id"
                            style="display: inline-block; width: auto; margin-left: 10px;">
                        @foreach($libraryTemplates as $libraryTemplate)
                            <option value="{{ $libraryTemplate->id }}"
                                    @if((int) old('email_template_id', $templateId) === $libraryTemplate->id) selected @endif>
                                {{ $libraryTemplate->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="source" id="source-own"
                       value="own" @if($chosen === 'own') checked @endif />
                <label class="form-check-label" for="source-own">Eigen tekst voor dit evenement</label>
            </div>

            <small class="text-muted">
                Onderwerp en inhoud hieronder worden alleen opgeslagen bij "Eigen tekst voor dit evenement".
                Ze beginnen van de versie die dit evenement nu verstuurt.
            </small>
        </div>
    @endsection

    @include('admin.emails.partials.editor', [
        'formAction' => action('Admin\EventEmailController@update', [ $event->id, $type ]),
        'previewAction' => action('Admin\EventEmailController@preview', [ $event->id, $type ]),
        'previewCaption' => 'Met de gegevens van dit evenement en een verzonnen speler.',
        'cancelUrl' => action('Admin\EventEmailController@index', [ $event->id ])
    ])

@endsection
