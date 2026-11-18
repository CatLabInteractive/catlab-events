<div class="form-row">
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Tekst', 'name' => $rowName . '[label]', 'value' => $row['label'] ?? '', 'error' => $rowError . '.label', 'max' => 40, 'required' => true ] ])
    </div>
    <div class="col-md-5">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Link', 'name' => $rowName . '[url]', 'value' => $row['url'] ?? '', 'error' => $rowError . '.url', 'max' => 1024, 'required' => true, 'placeholder' => '/calendar, https://..., mailto:...' ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Stijl', 'name' => $rowName . '[style]', 'value' => $row['style'] ?? 'primary', 'error' => $rowError . '.style', 'options' => [ 'primary' => 'Opvallend', 'secondary' => 'Rustig' ] ] ])
    </div>
</div>
