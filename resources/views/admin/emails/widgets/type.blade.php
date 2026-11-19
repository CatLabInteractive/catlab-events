{{-- App\Services\Admin\Widgets\EmailTypeWidget --}}
<div class="form-group">
    @include('charonfrontend::widgets.label')

    <select class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}"
            onchange="window.location.href = '{{ $reloadUrl }}?type=' + encodeURIComponent(this.value);">
        @foreach($field->getOptions() as $value => $display)
            <option value="{{ $value }}"{{ (string) $value === $selected ? ' selected' : '' }}>{{ $display }}</option>
        @endforeach
    </select>
    <small class="text-muted">Een andere mail kiezen laadt de standaardtekst van die mail.</small>
</div>
