{{-- App\Cms\Widgets\SlugWidget: the last part of an address, with the rest of the address in front. --}}
<?php use CatLab\CharonFrontend\Support\FormValue; ?>
<div class="form-group">
    @include('charonfrontend::widgets.label')

    <div class="input-group cms-slug">
        @if($prefix)
            <div class="input-group-prepend">
                <span class="input-group-text cms-slug-prefix" title="{{ $prefix }}">{{ $prefix }}</span>
            </div>
        @endif
        <input type="text" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}" value="{{ $values[0] ?? '' }}"{!! FormValue::attributes(array_merge([ 'class' => 'form-control' ], $attributes)) !!}>
    </div>
</div>

@once
    @push('scripts')
        <style>
            .cms-slug .input-group-prepend { max-width: 60%; min-width: 0; }
            .cms-slug-prefix { display: block; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            @media (max-width: 575.98px) { .cms-slug .input-group-prepend { max-width: 50%; } }
        </style>
    @endpush
@endonce
