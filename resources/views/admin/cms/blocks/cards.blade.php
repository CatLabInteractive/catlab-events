<div class="form-row">
    <div class="col-md-9">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120 ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Kolommen', 'name' => $name . '[columns]', 'value' => $data['columns'] ?? 3, 'error' => $error . '.columns', 'options' => [ 2 => '2', 3 => '3', 4 => '4' ] ] ])
    </div>
</div>
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Inleiding', 'name' => $name . '[intro]', 'value' => $data['intro'] ?? '', 'error' => $error . '.intro', 'max' => 500, 'rows' => 2 ] ])
@include('admin.cms.blocks._repeater', [ 'repeater' => [ 'key' => 'items', 'label' => 'Kaarten', 'name' => $name, 'error' => $error, 'rows' => $data['items'] ?? [], 'max' => 12, 'row' => 'admin.cms.blocks._card_row', 'add' => 'Kaart toevoegen' ] ])
