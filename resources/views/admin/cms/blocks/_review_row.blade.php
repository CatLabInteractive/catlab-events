@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Citaat', 'name' => $rowName . '[quote]', 'value' => $row['quote'] ?? '', 'error' => $rowError . '.quote', 'max' => 1000, 'rows' => 2, 'required' => true ] ])
<div class="form-row">
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Auteur', 'name' => $rowName . '[author]', 'value' => $row['author'] ?? '', 'error' => $rowError . '.author', 'max' => 120 ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Bron', 'name' => $rowName . '[source]', 'value' => $row['source'] ?? '', 'error' => $rowError . '.source', 'max' => 120, 'placeholder' => 'Google, Facebook, ...' ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Link', 'name' => $rowName . '[url]', 'value' => $row['url'] ?? '', 'error' => $rowError . '.url', 'max' => 1024 ] ])
    </div>
</div>
<div class="form-row">
    <div class="col-md-8">
        @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Logo of foto (optioneel)', 'name' => $rowName . '[image_id]', 'value' => $row['image_id'] ?? null, 'error' => $rowError . '.image_id' ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._colour_field', [ 'field' => [ 'label' => 'Kleur', 'name' => $rowName . '[colour]', 'value' => $row['colour'] ?? '', 'error' => $rowError . '.colour', 'none' => 'Automatisch' ] ])
    </div>
</div>
