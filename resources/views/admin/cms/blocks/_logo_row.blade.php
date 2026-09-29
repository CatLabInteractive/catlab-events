<div class="form-row">
    <div class="col-md-6">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Naam', 'name' => $rowName . '[name]', 'value' => $row['name'] ?? '', 'error' => $rowError . '.name', 'max' => 120, 'required' => true ] ])
    </div>
    <div class="col-md-6">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Link', 'name' => $rowName . '[url]', 'value' => $row['url'] ?? '', 'error' => $rowError . '.url', 'max' => 1024 ] ])
    </div>
</div>
@include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Logo', 'name' => $rowName . '[image_id]', 'value' => $row['image_id'] ?? null, 'error' => $rowError . '.image_id' ] ])
