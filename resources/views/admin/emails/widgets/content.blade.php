{{--
    App\Services\Admin\Widgets\EmailContentWidget: editor, placeholders and a
    sandboxed preview. $content, $placeholders, $previewAction.
--}}
<div class="form-group email-content-widget" data-email-editor>
    @include('charonfrontend::widgets.label', [ 'for' => $field->getId() ])

    <p style="margin-bottom: 5px;">
        <button type="button" class="btn btn-sm btn-secondary" data-email-toggle>Toon HTML</button>
    </p>

    {{-- The editor holds admin-written HTML; the textarea is what gets posted. --}}
    <div contenteditable="true" data-email-rich
         style="border: 1px solid #ddd; padding: 15px; min-height: 300px; background: #fff;">{!! $content !!}</div>
    <textarea name="{{ $field->getInputName() }}" id="{{ $field->getId() }}" class="form-control" data-email-source
              style="display: none; min-height: 400px; font-family: monospace;">{{ $content }}</textarea>

    <h4 class="mt-3">Beschikbare velden</h4>
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

    <h4>Voorbeeld</h4>
    <p>
        <button type="submit" class="btn btn-sm btn-primary"
                formaction="{{ $previewAction }}" formmethod="post"
                formtarget="email-preview" formnovalidate>
            Voorbeeld vernieuwen
        </button>
        <small class="text-muted ml-2">Met verzonnen gegevens. Toont je wijzigingen zonder ze op te slaan.</small>
    </p>
    <iframe name="email-preview" id="email-preview"
            src="{{ $previewAction }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd;"
            sandbox=""
            title="Voorbeeld van de mail"></iframe>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-email-editor]').forEach(function (widget) {
                    var editor = widget.querySelector('[data-email-rich]');
                    var content = widget.querySelector('[data-email-source]');
                    var toggle = widget.querySelector('[data-email-toggle]');
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

                    // Both saving and the preview post the textarea.
                    var form = widget.closest('form');
                    if (form) {
                        form.addEventListener('submit', function () {
                            if (!showingSource) {
                                content.value = editor.innerHTML;
                            }
                        });
                    }
                });
            })();
        </script>
    @endpush
@endonce
