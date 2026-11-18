@if (Session::has('message'))
    <div class="kv-wrap kv-flash">
        <p class="alert alert-info" role="status">{{ Session::get('message') }}</p>
    </div>
@endif
