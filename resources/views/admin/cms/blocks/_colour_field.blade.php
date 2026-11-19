{{--
    A colour select for a block or item: none, the light band colour or one
    of the organisation's palette colours.
    $field: [ 'label', 'name', 'value', 'error', 'none' => label for no colour ]
--}}
@php
    $colourOptions = [ '' => $field['none'] ?? 'Geen' ];
    foreach (organisation()->getColourPalette()->getFields() as $colourIndex => $colourField) {
        $colourOptions[(string) $colourIndex] = 'Kleur ' . ($colourIndex + 1) . ' (' . $colourField['field'] . ')';
    }
    $colourOptions['peach'] = 'Lichte band';
@endphp
@include('admin.cms.blocks._field', [ 'field' => array_merge($field, [ 'input' => 'select', 'options' => $colourOptions ]) ])
