{{-- App\Cms\Widgets\ImageWidget: an asset id, chosen with the CMS image picker. --}}
@include('admin.cms.widgets._editor_assets')

<div data-cms-editor
     data-cms-upload-url="{{ action('Admin\CmsAssetController@upload') }}"
     data-cms-assets-url="{{ action('Admin\CmsAssetController@index') }}">
    @include('admin.cms.blocks._image_field', [ 'field' => [ 'label' => $field->getLabel(), 'name' => $field->getInputName(), 'value' => $value ] ])
</div>
