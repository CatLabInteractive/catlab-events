@include('admin.cms.blocks._field', [ 'field' => [ 'input' => 'html', 'label' => 'Tekst', 'name' => $name . '[html]', 'value' => $data['html'] ?? '', 'error' => $error . '.html', 'rows' => 12 ] ])
