{{--
    App\Services\Admin\Widgets\LayoutHtmlWidget: the layout HTML under
    "Geavanceerd", and the preview of the whole form.
    $html, $defaultHtml, $hasCustomHtml, $open, $placeholders, $previewAction.
--}}
<div data-layout-html>
    <details style="margin-bottom: 15px;" @if($open) open @endif>
        <summary><strong>Geavanceerd</strong></summary>

        <div class="form-group" style="margin-top: 10px;">
            <label for="{{ $field->getId() }}">{{ $field->getLabel() }}</label>
            <p class="text-muted" style="margin-bottom: 5px;">
                Je vertrekt van de standaardlayout. Laat je de tekst ongewijzigd, dan volg je latere verbeteringen van de standaard.
                De layout moet precies één keer <code>&#123;&#123; content &#125;&#125;</code> bevatten.
            </p>
            <textarea class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}" rows="16"
                      style="font-family: monospace;" data-layout-source>{{ $html }}</textarea>
            <textarea hidden data-layout-default>{{ $defaultHtml }}</textarea>

            @if($hasCustomHtml)
                <p style="margin-top: 5px;">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-layout-reset>Terug naar de standaardlayout</button>
                    <small class="text-muted ml-2">Zet de standaardlayout terug in het veld. Je eigen HTML gaat pas verloren wanneer je opslaat.</small>
                </p>
            @endif
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

    <h4>Voorbeeld</h4>
    <p>
        <button type="submit" class="btn btn-sm btn-primary"
                formaction="{{ $previewAction }}" formmethod="post"
                formtarget="layout-preview" formnovalidate data-layout-preview>
            Voorbeeld vernieuwen
        </button>
        <small class="text-muted ml-2">Met verzonnen gegevens. Toont je wijzigingen zonder ze op te slaan.</small>
    </p>
    <iframe name="layout-preview" id="layout-preview"
            src="{{ $previewAction }}"
            style="width: 100%; height: 600px; border: 1px solid #ddd; margin-bottom: 15px;"
            sandbox=""
            title="Voorbeeld van de layout"></iframe>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-layout-html]').forEach(function (widget) {
                    var form = widget.closest('form');
                    var source = widget.querySelector('[data-layout-source]');
                    var preview = widget.querySelector('[data-layout-preview]');
                    var reset = widget.querySelector('[data-layout-reset]');
                    var timer = null;

                    // Post the draft to the preview frame (never to the save action).
                    function schedulePreview() {
                        clearTimeout(timer);
                        timer = setTimeout(function () { preview.click(); }, 400);
                    }

                    if (reset) {
                        reset.addEventListener('click', function () {
                            source.value = widget.querySelector('[data-layout-default]').value;
                            schedulePreview();
                        });
                    }

                    // The colour and the footer text show up as you type.
                    if (form) {
                        form.addEventListener('input', function (event) {
                            if (event.target !== source) {
                                schedulePreview();
                            }
                        });
                    }
                });
            })();
        </script>
    @endpush
@endonce
