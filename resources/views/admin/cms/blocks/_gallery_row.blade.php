{{-- One photo of a gallery: a compact tile (the rows sit in a grid, admin.scss). --}}
<div class="cms-gallery-row">
    @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => 'Foto', 'name' => $rowName . '[image_id]', 'value' => $row['image_id'] ?? null, 'error' => $rowError . '.image_id' ] ])
    @include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Onderschrift', 'name' => $rowName . '[caption]', 'value' => $row['caption'] ?? '', 'error' => $rowError . '.caption', 'max' => 200 ] ])
</div>
