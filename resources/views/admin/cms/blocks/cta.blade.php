<div class="form-row">
    <div class="col-md-8">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120, 'required' => true ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Achtergrond', 'name' => $name . '[background]', 'value' => $data['background'] ?? 'light', 'error' => $error . '.background', 'options' => [ 'light' => 'Licht', 'dark' => 'Donker', 'primary' => 'Huisstijlkleur' ] ] ])
    </div>
</div>
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Tekst', 'name' => $name . '[text]', 'value' => $data['text'] ?? '', 'error' => $error . '.text', 'max' => 500, 'rows' => 2 ] ])
@include('admin.cms.blocks._repeater', [ 'repeater' => [ 'key' => 'buttons', 'label' => 'Knoppen', 'name' => $name, 'error' => $error, 'rows' => $data['buttons'] ?? [], 'max' => 2, 'row' => 'admin.cms.blocks._button_row', 'add' => 'Knop toevoegen' ] ])
