@include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120, 'required' => true ] ])
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Ondertitel', 'name' => $name . '[subtitle]', 'value' => $data['subtitle'] ?? '', 'error' => $error . '.subtitle', 'max' => 300, 'rows' => 2 ] ])
<div class="form-row">
    <div class="col-md-8">
        @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Achtergrondafbeelding', 'name' => $name . '[image_id]', 'value' => $data['image_id'] ?? null, 'error' => $error . '.image_id' ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Uitlijning', 'name' => $name . '[align]', 'value' => $data['align'] ?? 'center', 'error' => $error . '.align', 'options' => [ 'center' => 'Gecentreerd', 'left' => 'Links' ] ] ])
    </div>
</div>
@include('admin.cms.blocks._repeater', [ 'repeater' => [ 'key' => 'buttons', 'label' => 'Knoppen', 'name' => $name, 'error' => $error, 'rows' => $data['buttons'] ?? [], 'max' => 3, 'row' => 'admin.cms.blocks._button_row', 'add' => 'Knop toevoegen' ] ])
