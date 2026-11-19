{{-- App\Cms\Widgets\HtmlWidget: rich text (TinyMCE, cms-editor.js). --}}
@include('admin.cms.widgets._editor_assets')

<div class="form-group" data-cms-editor
     data-cms-upload-url="{{ action('Admin\CmsAssetController@upload') }}"
     data-cms-assets-url="{{ action('Admin\CmsAssetController@index') }}">
    @include('charonfrontend::widgets.label')

    <textarea id="{{ $field->getId() }}" name="{{ $field->getInputName() }}" class="form-control cms-html" rows="16">{{ $value }}</textarea>
</div>
