@include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120 ] ])
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'html', 'label' => 'Tekst', 'name' => $name . '[html]', 'value' => $data['html'] ?? '', 'error' => $error . '.html' ] ])
<div class="form-row">
    <div class="col-md-8">
        @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Afbeelding', 'name' => $name . '[image_id]', 'value' => $data['image_id'] ?? null, 'error' => $error . '.image_id' ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Afbeelding staat', 'name' => $name . '[image_position]', 'value' => $data['image_position'] ?? 'right', 'error' => $error . '.image_position', 'options' => [ 'right' => 'Rechts', 'left' => 'Links' ] ] ])
    </div>
</div>
@php($button = is_array($data['button'] ?? null) ? $data['button'] : [])
<div class="form-row">
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Knop: tekst (optioneel)', 'name' => $name . '[button][label]', 'value' => $button['label'] ?? '', 'error' => $error . '.button.label', 'max' => 40 ] ])
    </div>
    <div class="col-md-8">
        @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Knop: link', 'name' => $name . '[button][url]', 'value' => $button['url'] ?? '', 'error' => $error . '.button.url', 'max' => 1024, 'placeholder' => '/calendar, https://..., mailto:...' ] ])
    </div>
</div>
