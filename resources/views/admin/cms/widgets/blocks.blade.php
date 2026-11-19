{{--
    App\Cms\Widgets\BlocksWidget: the section blocks of a page translation.
    $blocks ([ [ id, type, data ] ]), $blockTypes (BlockType by type), $blocksName (name prefix)
--}}
@include('admin.cms.widgets._editor_assets')

<div class="form-group cms-editor" data-cms-editor data-cms-name="{{ $blocksName }}"
     data-cms-upload-url="{{ action('Admin\CmsAssetController@upload') }}"
     data-cms-assets-url="{{ action('Admin\CmsAssetController@index') }}">
    <label class="d-block">{{ $field->getLabel() }}</label>

    @if(count($blocks) > 1)
        <p class="mb-2"><button type="button" class="btn btn-sm btn-link p-0" data-cms-action="toggle-all">Alles open- of dichtklappen</button></p>
    @endif

    {{-- Present even without blocks, so an emptied list is read as empty. --}}
    <input type="hidden" name="{{ $blocksName }}" value="" />

    <div class="cms-blocks" data-cms-blocks>
        @foreach($blocks as $i => $block)
            @php($blockType = is_string($block['type'] ?? null) ? ($blockTypes[$block['type']] ?? null) : null)
            @if($blockType)
                {{-- With several sections, each folds to one line; all stay open after an error. --}}
                @include('admin.cms.blocks._block', [
                    'i' => $i, 'block' => $block, 'blockType' => $blockType,
                    'collapsed' => count($blocks) > 1 && !$errors->has($field->getDotName()) && !$errors->has('blocks.' . $i . '.*'),
                ])
            @endif
        @endforeach
    </div>

    <div class="form-inline mb-2 p-2 bg-light border rounded">
        <label class="mr-2" for="{{ $field->getId('add') }}">Sectie toevoegen:</label>
        <select id="{{ $field->getId('add') }}" class="form-control form-control-sm mr-2" data-cms-block-select>
            @foreach($blockTypes as $typeKey => $blockType)
                <option value="{{ $typeKey }}">{{ $blockType->label() }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-primary" data-cms-action="add-block">Toevoegen</button>
    </div>

    @foreach($blockTypes as $typeKey => $blockType)
        <template data-block-template="{{ $typeKey }}" data-block-type="{{ $typeKey }}">
            @include('admin.cms.blocks._block', [
                'i' => '__INDEX__',
                'block' => [ 'id' => '__ID__', 'type' => $typeKey, 'data' => $blockType->defaults() ],
                'blockType' => $blockType,
            ])
        </template>
    @endforeach
</div>

@once
    @push('scripts')
        <style>
            .cms-block-collapsed > .card-body { display: none; }
            .cms-block-collapsed > .card-header { border-bottom: 0; }
            .cms-block-caret { display: inline-block; width: 1em; transition: transform .15s; }
            .cms-block-collapsed .cms-block-caret { transform: rotate(-90deg); }
            .cms-block-toggle { min-width: 0; min-height: 36px; }
            .cms-block-summary { min-width: 0; font-weight: normal; }
        </style>
    @endpush
@endonce
