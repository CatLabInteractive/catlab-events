<div class="form-row">
    <div class="col-md-6">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120 ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Weergave', 'name' => $name . '[layout]', 'value' => $data['layout'] ?? 'grid', 'error' => $error . '.layout', 'options' => [ 'grid' => 'Raster', 'mosaic' => 'Mozaïek', 'featured' => 'Eerste groot' ] ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Eerst tonen', 'name' => $name . '[visible]', 'value' => $data['visible'] ?? 12, 'error' => $error . '.visible', 'options' => [ 6 => '6 foto\'s', 9 => '9 foto\'s', 12 => '12 foto\'s', 18 => '18 foto\'s', 24 => '24 foto\'s', 0 => 'Alle foto\'s' ], 'help' => 'De rest achter een knop.' ] ])
    </div>
</div>
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Inleiding', 'name' => $name . '[intro]', 'value' => $data['intro'] ?? '', 'error' => $error . '.intro', 'max' => 500, 'rows' => 2 ] ])
<p class="small text-muted mb-1">Raster: gelijke tegels. Mozaïek: elke foto in haar eigen vorm. Eerste groot: raster met de eerste foto groot. Op de site opent een klik de foto groot. Het onderschrift is ook de beschrijving voor schermlezers.</p>
@include('admin.cms.blocks._repeater', [ 'repeater' => [ 'key' => 'items', 'label' => 'Foto\'s', 'name' => $name, 'error' => $error, 'rows' => $data['items'] ?? [], 'max' => \App\Cms\Blocks\Types\Gallery::MAX_ITEMS, 'row' => 'admin.cms.blocks._gallery_row', 'add' => 'Foto toevoegen', 'add_many' => 'Meerdere foto\'s kiezen of uploaden' ] ])
