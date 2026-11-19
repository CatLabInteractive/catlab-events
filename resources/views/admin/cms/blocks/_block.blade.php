{{--
    One section block in the editor: frame, hidden id/type and the type's
    own fields. Also rendered inside <template> with $i = '__INDEX__' and
    id '__ID__' (cms-editor.js fills those in).
    $i, $block ([id, type, data]), $blockType (BlockType), $blocksName (name prefix, 'fields[blocks]'),
    $collapsed (folded to its header line)
--}}
@php
    $blockName = ($blocksName ?? 'blocks') . '[' . $i . ']';
    $blockError = 'blocks.' . $i;
    $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
@endphp
<div class="card mb-3 cms-block{{ !empty($collapsed) ? ' cms-block-collapsed' : '' }}" data-block-type="{{ $blockType->type() }}">
    <div class="card-header d-flex align-items-center py-2">
        <button type="button" class="btn btn-link text-reset text-left text-decoration-none p-0 mr-2 d-flex align-items-center flex-grow-1 cms-block-toggle"
                data-cms-action="toggle" aria-expanded="{{ !empty($collapsed) ? 'false' : 'true' }}">
            <span class="cms-block-caret" aria-hidden="true">&#9662;</span>
            <strong class="ml-2 flex-shrink-0">{{ $blockType->label() }}</strong>
            <span class="cms-block-summary text-muted ml-2 text-truncate" data-cms-block-summary></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-1" data-cms-action="up" title="Omhoog" aria-label="Omhoog">&uarr;</button>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-1" data-cms-action="down" title="Omlaag" aria-label="Omlaag">&darr;</button>
        <button type="button" class="btn btn-sm btn-outline-danger ml-1" data-cms-action="remove" title="Verwijder deze sectie" aria-label="Verwijder deze sectie"><span class="d-none d-sm-inline">Verwijder</span><span class="d-sm-none" aria-hidden="true">&times;</span></button>
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

        @if($blockType->hasColour())
            @include('admin.cms.blocks._colour_field', [ 'field' => [ 'label' => 'Achtergrond', 'name' => $blockName . '[data][colour]', 'value' => $blockData['colour'] ?? '', 'error' => $blockError . '.data.colour', 'none' => 'Geen (pagina-achtergrond)' ] ])
        @endif
    </div>
</div>
