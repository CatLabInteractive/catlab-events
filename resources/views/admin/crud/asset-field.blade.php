<?php
$name = $assetField['name'];
$current = $assetField['current'];
$selected = (string) old('assets.' . $name . '.id', $current ? $current->id : '');
$id = 'asset-' . $name;
?>
<div class="form-group asset-field" data-asset-field>
    <label for="{{ $id }}-file">{{ $assetField['label'] }}</label>

    <div class="asset-field-body">
        <div class="asset-field-preview">
            <img src="{{ $assetField['currentUrl'] }}" alt="" data-asset-preview{!! $assetField['currentUrl'] ? '' : ' hidden' !!}>
            <span class="asset-field-empty" data-asset-empty{!! $assetField['currentUrl'] ? ' hidden' : '' !!}>No file</span>
        </div>

        <div class="asset-field-controls">
            <div class="mb-2">
                <small class="form-text text-muted mt-0">Upload a new file</small>
                <input type="file" class="form-control-file" id="{{ $id }}-file" name="assets[{{ $name }}][file]" accept="image/*" data-asset-file>
            </div>

            <div class="mb-2">
                <small class="form-text text-muted mt-0">or pick an existing one</small>
                <select class="form-control form-control-sm" id="{{ $id }}-id" name="assets[{{ $name }}][id]">
                    <option value="">(none)</option>
                    @foreach($assetField['options'] as $value => $display)
                        <option value="{{ $value }}"{{ (string) $value === $selected ? ' selected' : '' }}>{{ $display }}</option>
                    @endforeach
                </select>
            </div>

            @if($current)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="{{ $id }}-remove" name="assets[{{ $name }}][remove]" value="1"{{ old('assets.' . $name . '.remove') ? ' checked' : '' }}>
                    <label class="form-check-label" for="{{ $id }}-remove">Remove {{ strtolower($assetField['label']) }}</label>
                </div>
            @endif
        </div>
    </div>
</div>
