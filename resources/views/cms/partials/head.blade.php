{{-- Extra <head> tags for CMS pages (pushed onto the 'head' stack). --}}
@if($preview && config('seo.indexable'))
    <meta name="robots" content="noindex">
@endif
