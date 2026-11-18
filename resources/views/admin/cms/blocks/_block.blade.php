{{--
    One section block in the editor: frame, hidden id/type and the type's
    own fields. Also rendered inside <template> with $i = '__INDEX__' and
    id '__ID__' (cms-editor.js fills those in).
    $i, $block ([id, type, data]), $blockType (BlockType)
--}}
@php
    $blockName = 'blocks[' . $i . ']';
    $blockError = 'blocks.' . $i;
    $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
@endphp
<div class="card mb-3 cms-block" data-block-type="{{ $blockType->type() }}">
    <div class="card-header d-flex align-items-center py-2">
        <strong class="mr-auto">{{ $blockType->label() }}</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-1" data-cms-action="up" title="Omhoog">&uarr;</button>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-1" data-cms-action="down" title="Omlaag">&darr;</button>
        <button type="button" class="btn btn-sm btn-outline-danger ml-1" data-cms-action="remove" title="Verwijder deze sectie">Verwijder</button>
    </div>
    <div class="card-body">
        <input type="hidden" name="{{ $blockName }}[id]" value="{{ $block['id'] ?? '' }}" />
        <input type="hidden" name="{{ $blockName }}[type]" value="{{ $blockType->type() }}" />

        @foreach([ $blockError . '.id', $blockError . '.type', $blockError . '.data' ] as $key)
            @if($errors->has($key))
                <div class="alert alert-danger py-1">{{ $errors->first($key) }}</div>
            @endif
        @endforeach

        @include($blockType->formView(), [
            'name' => $blockName . '[data]',
            'error' => $blockError . '.data',
            'data' => $blockData,
        ])
    </div>
</div>
