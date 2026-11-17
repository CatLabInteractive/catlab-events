@extends('layouts/admin')

@section('content')

    <h2>Layout van de mails</h2>

    @include('admin.emails.partials.tabs', [ 'active' => 'layout' ])

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p>Elke mail gaat in dezelfde layout de deur uit. Kies hier de accentkleur en de voettekst, of vervang de hele layout.</p>

    <form action="{{ action('Admin\EmailLayoutController@update') }}" method="post" id="email-layout-form">
        {{ csrf_field() }}

        <div class="form-group">
            <label for="accent_color_text">Accentkleur</label>
            <div class="input-group" style="max-width: 250px;">
                <input type="color" class="form-control" id="accent_color_picker" style="max-width: 60px; padding: 2px;"
                       value="{{ old('accent_color', $accentColor) }}" />
                <input type="text" class="form-control" id="accent_color_text" name="accent_color"
                       value="{{ old('accent_color', $accentColor) }}" maxlength="7" placeholder="#4cb050" />
            </div>
        </div>

        <div class="form-group">
            <label for="footer_text">Voettekst</label>
            <textarea class="form-control" id="footer_text" name="footer_text" rows="3" maxlength="2000">{{ old('footer_text', $footerText) }}</textarea>
            <small class="text-muted">Staat boven de contactgegevens van de organisatie.</small>
        </div>

        <details style="margin-bottom: 15px;" @if($errors->has('custom_html') || old('custom_html') !== null || $hasCustomHtml) open @endif>
            <summary><strong>Geavanceerd</strong></summary>

            <div class="form-group" style="margin-top: 10px;">
                <label for="custom_html">HTML van de layout</label>
                <p class="text-muted" style="margin-bottom: 5px;">
                    Je vertrekt van de standaardlayout. Laat je de tekst ongewijzigd, dan volg je latere verbeteringen van de standaard.
                    De layout moet precies één keer <code>&#123;&#123; content &#125;&#125;</code> bevatten.
                </p>
                <textarea class="form-control" id="custom_html" name="custom_html" rows="16"
                          style="font-family: monospace;">{{ old('custom_html', $customHtml) }}</textarea>
            </div>

            <h4>Beschikbare velden</h4>
            <table class="table table-sm">
                @foreach($placeholders as $placeholder => $description)
                    <tr>
                        <td style="width: 250px;"><code>&#123;&#123; {{ $placeholder }} &#125;&#125;</code></td>
                        <td>{{ $description }}</td>
                    </tr>
                @endforeach
            </table>
        </details>

        <p>
            <button type="submit" class="btn btn-success">Opslaan</button>
            <button type="submit" class="btn btn-primary"
                    formaction="{{ action('Admin\EmailLayoutController@preview') }}"
                    formtarget="layout-preview" formnovalidate>
                Voorbeeld
            </button>
        </p>
    </form>

    @if($hasCustomHtml)
        <form action="{{ action('Admin\EmailLayoutController@reset') }}" method="post"
              onsubmit="return confirm('Terug naar de standaardlayout? Je eigen HTML gaat verloren.');">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-sm btn-outline-danger">Terug naar de standaardlayout</button>
        </form>
    @endif

    <h4 style="margin-top: 20px;">Voorbeeld</h4>
    <p><small class="text-muted">Met verzonnen gegevens. Klik op "Voorbeeld" om je wijzigingen te bekijken zonder ze op te slaan.</small></p>
    <iframe name="layout-preview" id="layout-preview"
            src="{{ action('Admin\EmailLayoutController@preview') }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd;"
            sandbox=""
            title="Voorbeeld van de layout"></iframe>

    <script>
        (function () {
            var picker = document.getElementById('accent_color_picker');
            var text = document.getElementById('accent_color_text');

            var form = document.getElementById('email-layout-form');
            var footer = document.getElementById('footer_text');
            var timer = null;

            // Post the draft to the preview frame (never to the save action).
            function refreshPreview() {
                var oldAction = form.action;
                var oldTarget = form.target;
                form.action = {!! json_encode(action('Admin\EmailLayoutController@preview')) !!};
                form.target = 'layout-preview';
                HTMLFormElement.prototype.submit.call(form);
                form.action = oldAction;
                form.target = oldTarget;
            }

            function schedulePreview() {
                clearTimeout(timer);
                timer = setTimeout(refreshPreview, 400);
            }

            picker.addEventListener('input', function () { text.value = picker.value; schedulePreview(); });
            text.addEventListener('input', function () {
                if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
                    picker.value = text.value;
                }
                schedulePreview();
            });
            footer.addEventListener('input', schedulePreview);
        })();
    </script>

@endsection
