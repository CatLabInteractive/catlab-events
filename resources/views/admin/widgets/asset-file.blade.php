{{--
    App\Services\Admin\AssetFileWidget: the Charon file widget with the
    existing assets as a grid of thumbnails. Each tile is a radio button that
    posts [id], as the select of the original widget did. admin.js updates the
    preview and filters the grid.
--}}
<div class="form-group charon-file-field asset-file-field" data-charon-file data-asset-file>
    @include('charonfrontend::widgets.label', [ 'for' => $field->getId('file') ])

    <div class="asset-file-body">
        <div class="asset-file-preview">
            <img src="{{ $previewUrl }}" alt="" data-charon-file-preview{!! $previewUrl ? '' : ' hidden' !!}>
            <span class="text-muted small py-4 d-block" data-charon-file-empty{!! $previewUrl ? ' hidden' : '' !!}>{{ __('charonfrontend::crud.no_file') }}</span>
        </div>

        <div class="asset-file-controls">
            @if($kept)
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="{{ $field->getId('kept') }}" name="{{ $field->getInputName('[kept]') }}" value="{{ $kept['token'] }}" checked>
                    <label class="form-check-label" for="{{ $field->getId('kept') }}">{{ __('charonfrontend::crud.use_kept_upload') }} <strong>{{ $kept['name'] }}</strong></label>
                </div>
            @endif

            <div class="mb-2">
                <small class="form-text text-muted mt-0">{{ __('charonfrontend::crud.upload_new') }}</small>
                <input type="file" class="form-control-file" id="{{ $field->getId('file') }}" name="{{ $field->getInputName('[file]') }}" accept="{{ $accept }}" data-asset-file-upload>
            </div>

            <div class="mb-2">
                <div class="d-flex align-items-center mb-1">
                    <small class="form-text text-muted mt-0 mr-2">{{ __('charonfrontend::crud.pick_existing') }}</small>
                    @if(count($tiles) > 8)
                        <input type="search" class="form-control form-control-sm asset-file-search" placeholder="Zoek op bestandsnaam" aria-label="Zoek op bestandsnaam" data-asset-file-search>
                    @endif
                </div>

                <div class="asset-file-grid" role="radiogroup" aria-label="{{ $field->getLabel() }}">
                    <label class="asset-file-tile asset-file-tile-none" title="{{ __('charonfrontend::crud.none') }}">
                        <input type="radio" name="{{ $field->getInputName('[id]') }}" value=""{{ $selected === '' ? ' checked' : '' }} data-preview="">
                        <span class="asset-file-thumb">{{ __('charonfrontend::crud.none') }}</span>
                        <span class="asset-file-name">&nbsp;</span>
                    </label>
                    @foreach($tiles as $tile)
                        <label class="asset-file-tile" title="{{ $tile['label'] }}" data-asset-file-name="{{ \Illuminate\Support\Str::lower($tile['name']) }}">
                            <input type="radio" name="{{ $field->getInputName('[id]') }}" value="{{ $tile['id'] }}"{{ $tile['id'] === $selected ? ' checked' : '' }} data-preview="{{ $tile['preview'] }}">
                            <span class="asset-file-thumb">
                                @if($tile['thumbnail'])
                                    <img src="{{ $tile['thumbnail'] }}" alt="" loading="lazy">
                                @else
                                    #{{ $tile['id'] }}
                                @endif
                            </span>
                            <span class="asset-file-name">{{ $tile['name'] }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-muted small mt-1 mb-0" data-asset-file-nothing hidden>Geen afbeeldingen gevonden.</p>
            </div>

            @if($current !== null)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="{{ $field->getId('remove') }}" name="{{ $field->getInputName('[remove]') }}" value="1"{{ $field->old('remove') ? ' checked' : '' }}>
                    <label class="form-check-label" for="{{ $field->getId('remove') }}">{{ __('charonfrontend::crud.remove_field', [ 'field' => \Illuminate\Support\Str::lower($field->getLabel()) ]) }}</label>
                </div>
            @endif
        </div>
    </div>
</div>
