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

    <form action="{{ action('Admin\EmailTemplateController@update', [ $type ]) }}" method="post" id="email-edit-form">
        {{ csrf_field() }}

        <div class="form-group">
            <label for="subject">Onderwerp</label>
            <input type="text" class="form-control" id="subject" name="subject"
                   value="{{ old('subject', $subject) }}" maxlength="255" required />
        </div>

        <div class="form-group">
            <label>Inhoud</label>
            <p style="margin-bottom: 5px;">
                <button type="button" class="btn btn-sm btn-secondary" id="email-toggle-source">Toon HTML</button>
            </p>

            {{-- The editor holds admin-written HTML; the textarea is what gets posted. --}}
            <div id="email-editor" contenteditable="true"
                 style="border: 1px solid #ddd; padding: 15px; min-height: 300px; background: #fff;">{!! old('content', $content) !!}</div>
            <textarea name="content" id="email-content" class="form-control"
                      style="display: none; min-height: 400px; font-family: monospace;">{{ old('content', $content) }}</textarea>
        </div>

        <h4>Beschikbare velden</h4>
        <p>
            Deze velden worden ingevuld wanneer de mail verstuurd wordt. Je kan ze gebruiken in het onderwerp en in de inhoud.
        </p>
        <table class="table table-sm">
            @foreach($placeholders as $placeholder => $description)
                <tr>
                    <td style="width: 250px;"><code>&#123;&#123; {{ $placeholder }} &#125;&#125;</code></td>
                    <td>{{ $description }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin-top: 15px;">
            <button type="submit" class="btn btn-success">Opslaan</button>
            <button type="submit" class="btn btn-primary"
                    formaction="{{ action('Admin\EmailTemplateController@preview', [ $type ]) }}"
                    formtarget="email-preview" formnovalidate>
                Voorbeeld
            </button>
            <a class="btn btn-secondary" href="{{ action('Admin\EmailTemplateController@show', [ $type ]) }}">Annuleer</a>
        </p>
    </form>

    <h4>Voorbeeld</h4>
    <p><small class="text-muted">Met verzonnen gegevens. Klik op "Voorbeeld" om je wijzigingen te bekijken zonder ze op te slaan.</small></p>
    <iframe name="email-preview" id="email-preview"
            src="{{ action('Admin\EmailTemplateController@preview', [ $type ]) }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd;"
            sandbox=""
            title="Voorbeeld van de mail"></iframe>

    <script>
        (function () {
            var editor = document.getElementById('email-editor');
            var content = document.getElementById('email-content');
            var toggle = document.getElementById('email-toggle-source');
            var showingSource = false;

            toggle.addEventListener('click', function () {
                if (showingSource) {
                    editor.innerHTML = content.value;
                } else {
                    content.value = editor.innerHTML;
                }

                showingSource = !showingSource;
                editor.style.display = showingSource ? 'none' : '';
                content.style.display = showingSource ? '' : 'none';
                toggle.textContent = showingSource ? 'Toon opmaak' : 'Toon HTML';
            });

            // Both Opslaan and Voorbeeld post the textarea.
            document.getElementById('email-edit-form').addEventListener('submit', function () {
                if (!showingSource) {
                    content.value = editor.innerHTML;
                }
            });
        })();
    </script>

@endsection
