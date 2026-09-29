@include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Titel', 'name' => $name . '[title]', 'value' => $data['title'] ?? '', 'error' => $error . '.title', 'max' => 120 ] ])
@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'textarea', 'label' => 'Inleiding', 'name' => $name . '[intro]', 'value' => $data['intro'] ?? '', 'error' => $error . '.intro', 'max' => 500, 'rows' => 2 ] ])
<div class="form-row">
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'select', 'label' => 'Soort', 'name' => $name . '[event_type]', 'value' => $data['event_type'] ?? 'all', 'error' => $error . '.event_type', 'options' => [ 'all' => 'Alles', 'event' => 'Evenementen', 'package' => 'Quizpakketten' ] ] ])
    </div>
    <div class="col-md-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'number', 'label' => 'Aantal', 'name' => $name . '[limit]', 'value' => $data['limit'] ?? 4, 'error' => $error . '.limit', 'min' => 1, 'max' => 12, 'required' => true ] ])
    </div>
    <div class="col-md-4 pt-4">
        @include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'checkbox', 'label' => 'Toon uitverkochte evenementen', 'name' => $name . '[show_sold_out]', 'value' => $data['show_sold_out'] ?? true, 'error' => $error . '.show_sold_out' ] ])
    </div>
</div>
@include('admin.cms.blocks._field', [ 'field' => [ 'label' => 'Tekst als er niets gepland is', 'name' => $name . '[empty_text]', 'value' => $data['empty_text'] ?? '', 'error' => $error . '.empty_text', 'max' => 300 ] ])
