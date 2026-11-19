{{--
    A list of rows inside a block (cards, buttons, reviews, ...), edited with
    cms-editor.js. Names: {name}[{key}][{j}][field].
    $repeater: [ 'key', 'label', 'name' (the block's data name), 'error' (the block's data error key),
                 'rows', 'max', 'row' (row partial), 'add' (button label),
                 'add_many' (optional: label of a button that adds one row per picked image) ]
--}}
@php
    $rows = is_array($repeater['rows'] ?? null) ? array_values(array_filter($repeater['rows'], 'is_array')) : [];
    $listError = $errors->first($repeater['error'] . '.' . $repeater['key']);
@endphp
<div class="cms-repeater mb-3" data-repeater="{{ $repeater['key'] }}" data-max="{{ $repeater['max'] }}">
    <label class="mb-1 d-block">{{ $repeater['label'] }} <small class="text-muted">(max. {{ $repeater['max'] }})</small></label>
    @if($listError)
        <div class="invalid-feedback d-block">{{ $listError }}</div>
    @endif

    <div class="cms-repeater-rows">
        @foreach($rows as $j => $row)
            @include('admin.cms.blocks._row', [
                'rowView' => $repeater['row'],
                'rowName' => $repeater['name'] . '[' . $repeater['key'] . '][' . $j . ']',
                'rowError' => $repeater['error'] . '.' . $repeater['key'] . '.' . $j,
                'row' => $row,
            ])
        @endforeach
    </div>

    <template data-repeater-template>
        @include('admin.cms.blocks._row', [
            'rowView' => $repeater['row'],
            'rowName' => $repeater['name'] . '[' . $repeater['key'] . '][__ROW__]',
            'rowError' => '__none__',
            'row' => [],
        ])
    </template>

    <button type="button" class="btn btn-sm btn-outline-primary" data-cms-action="add-row">+ {{ $repeater['add'] }}</button>
    @if(!empty($repeater['add_many']))
        {{-- Rows with one image each: pick (or upload) several at once. --}}
        <button type="button" class="btn btn-sm btn-primary ml-1" data-cms-action="add-images">+ {{ $repeater['add_many'] }}</button>
    @endif
</div>
