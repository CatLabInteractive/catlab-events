<div class="form-row">
    <div class="col-md-6">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $rowName . '[title]', 'value' => $row['title'] ?? '', 'error' => $rowError . '.title', 'max' => 120, 'required' => true ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Icoon', 'name' => $rowName . '[icon]', 'value' => $row['icon'] ?? '', 'error' => $rowError . '.icon', 'max' => 40, 'placeholder' => 'trophy', 'help' => 'Font Awesome-naam zonder fa-' ] ])
    </div>
    <div class="col-md-3">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Link', 'name' => $rowName . '[url]', 'value' => $row['url'] ?? '', 'error' => $rowError . '.url', 'max' => 1024 ] ])
    </div>
</div>
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Tekst', 'name' => $rowName . '[text]', 'value' => $row['text'] ?? '', 'error' => $rowError . '.text', 'max' => 500, 'rows' => 2 ] ])
@include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Afbeelding', 'name' => $rowName . '[image_id]', 'value' => $row['image_id'] ?? null, 'error' => $rowError . '.image_id' ] ])
