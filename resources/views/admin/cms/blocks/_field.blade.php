{{--
    One form field of the page editor.
    $field: [ 'input' => text|textarea|html|select|number|checkbox, 'label', 'name', 'value',
              'error' => dot key for $errors, 'options' => [ value => label ], 'help', 'max',
              'min', 'required' => bool, 'placeholder' ]
--}}
@php
    $input = $field['input'] ?? 'text';
    $error = isset($field['error']) ? $errors->first($field['error']) : null;
    $value = $field['value'] ?? null;
    $class = 'form-control form-control-sm' . ($error ? ' is-invalid' : '');
@endphp
<div class="form-group">
    @if($input === 'checkbox')
        <div class="form-check">
            <input type="hidden" name="{{ $field['name'] }}" value="0" />
            <label class="form-check-label">
                <input type="checkbox" class="form-check-input{{ $error ? ' is-invalid' : '' }}" name="{{ $field['name'] }}" value="1" @if(filter_var($value, FILTER_VALIDATE_BOOLEAN)) checked @endif />
                {{ $field['label'] }}
            </label>
        </div>
    @else
        <label class="mb-1">{{ $field['label'] }}@if(!empty($field['required'])) <span class="text-danger">*</span>@endif</label>

        @if($input === 'textarea')
            <textarea class="{{ $class }}" name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 3 }}" @if(isset($field['max'])) maxlength="{{ $field['max'] }}" @endif>{{ is_scalar($value) ? $value : '' }}</textarea>
        @elseif($input === 'html')
            <textarea class="{{ $class }} cms-html" name="{{ $field['name'] }}" rows="{{ $field['rows'] ?? 8 }}">{{ is_scalar($value) ? $value : '' }}</textarea>
        @elseif($input === 'select')
            <select class="{{ $class }}" name="{{ $field['name'] }}">
                @foreach($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @if((string) $value === (string) $optionValue) selected @endif>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $input === 'number' ? 'number' : 'text' }}" class="{{ $class }}" name="{{ $field['name'] }}"
                   value="{{ is_scalar($value) ? $value : '' }}"
                   @if(isset($field['max']) && $input !== 'number') maxlength="{{ $field['max'] }}" @endif
                   @if(isset($field['max']) && $input === 'number') max="{{ $field['max'] }}" @endif
                   @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                   @if(isset($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif />
        @endif
    @endif

    @if($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @endif
    @if(!empty($field['help']))
        <small class="form-text text-muted">{{ $field['help'] }}</small>
    @endif
</div>
