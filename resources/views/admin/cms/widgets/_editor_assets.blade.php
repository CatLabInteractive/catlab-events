{{-- Once per page, for any CMS widget: TinyMCE and the image picker modal (cms-editor.js). --}}
@once
    @push('scripts')
        @include('admin.cms._image_picker')
        <script src="{{ asset('js/tinymce/tinymce.min.js') }}"></script>
    @endpush
@endonce
