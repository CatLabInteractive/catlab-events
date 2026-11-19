{{-- App\Services\Admin\Widgets\AccentColorWidget: a colour picker that fills the posted text field. $value. --}}
<div class="form-group" data-accent-color>
    @include('charonfrontend::widgets.label')

    <div class="input-group" style="max-width: 250px;">
        <input type="color" class="form-control" style="max-width: 60px; padding: 2px;"
               value="{{ $value }}" aria-label="{{ $field->getLabel() }}" data-accent-picker />
        <input type="text" class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}"
               value="{{ $value }}" maxlength="7" placeholder="#4cb050" data-accent-text />
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-accent-color]').forEach(function (widget) {
                    var picker = widget.querySelector('[data-accent-picker]');
                    var text = widget.querySelector('[data-accent-text]');

                    picker.addEventListener('input', function () {
                        text.value = picker.value;
                        text.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    text.addEventListener('input', function () {
                        if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
                            picker.value = text.value;
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
