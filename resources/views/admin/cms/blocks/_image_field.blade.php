{{--
    An image (assets.id) of the active organisation: a hidden input, a
    thumbnail, and buttons that open the image picker (cms-editor.js).
    $field: [ 'label', 'name', 'value', 'error' ]
--}}
@php
    $error = isset($field['error']) ? $errors->first($field['error']) : null;
    $assetId = is_numeric($field['value'] ?? null) ? (int) $field['value'] : null;
    $asset = $assetId ? \CatLab\CentralStorage\Client\Models\Asset::find($assetId) : null;
@endphp
<div class="form-group cms-image-field">
    <label class="mb-1 d-block">{{ $field['label'] }}</label>
    <input type="hidden" name="{{ $field['name'] }}" value="{{ $assetId }}" data-cms-image-input />
    <div class="d-flex align-items-center">
        <img src="{{ $asset ? $asset->getUrl([ 'width' => 160 ]) : '' }}" alt="" class="cms-image-preview mr-2 {{ $asset ? '' : 'd-none' }}" data-cms-image-preview />
        <span class="text-muted small mr-2 {{ $asset ? 'd-none' : '' }}" data-cms-image-empty>Geen afbeelding</span>
        <button type="button" class="btn btn-sm btn-outline-secondary mr-1" data-cms-action="pick-image">Kies afbeelding</button>
        <button type="button" class="btn btn-sm btn-outline-danger {{ $asset ? '' : 'd-none' }}" data-cms-action="clear-image">Verwijder</button>
    </div>
    @if($assetId && !$asset)
        <small class="form-text text-warning">De gekozen afbeelding (#{{ $assetId }}) bestaat niet meer.</small>
    @endif
    @if($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @endif
</div>
