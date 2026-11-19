<div class="form-row">
    <div class="col-md-8">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120 ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'number', 'label' => 'Aantal', 'name' => $name . '[limit]', 'value' => $data['limit'] ?? 3, 'error' => $error . '.limit', 'min' => 1, 'max' => 6, 'required' => true ] ])
    </div>
</div>
