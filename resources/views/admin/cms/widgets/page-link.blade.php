{{-- App\Cms\Widgets\PageLinkWidget: content page, or a menu link (fixed part or address). --}}
<div class="form-group">
    @include('charonfrontend::widgets.label', [ 'for' => $field->getId('kind') ])

    <select class="form-control" id="{{ $field->getId('kind') }}" name="{{ $field->getInputName('[kind]') }}">
        @foreach($kinds as $value => $display)
            <option value="{{ $value }}"{{ (string) $value === $kind ? ' selected' : '' }}>{{ $display }}</option>
        @endforeach
    </select>

    <label class="mt-2" for="{{ $field->getId('url') }}">Adres van de link</label>
    <small class="form-text field-description">Alleen bij "een ander adres".</small>
    <input type="text" class="form-control" id="{{ $field->getId('url') }}" name="{{ $field->getInputName('[url]') }}"
           value="{{ $url }}" maxlength="1024" placeholder="/kalender, https://...">
</div>
