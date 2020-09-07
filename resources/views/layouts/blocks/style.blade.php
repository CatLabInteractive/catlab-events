@if(
    !isset($_GET['noasynccss']) &&
    // is first page load?
    (!\Session::get('not_first_pageload')) &&
    config('app.env') === 'production' &&
    View::hasSection('critical-css')
)
    <?php \Session::put('not_first_pageload', 1); ?>
    @yield('critical-css')
    <script type="text/javascript">
        !function(a){"use strict";var b=function(b,c,d){var g,e=a.document,f=e.createElement("link");if(c)g=c;else{var h=(e.body||e.getElementsByTagName("head")[0]).childNodes;g=h[h.length-1]}var i=e.styleSheets;f.rel="stylesheet",f.href=b,f.media="only x",g.parentNode.insertBefore(f,c?g:g.nextSibling);var j=function(a){for(var b=f.href,c=i.length;c--;)if(i[c].href===b)return a();setTimeout(function(){j(a)})};return f.onloadcssdefined=j,j(function(){f.media=d||"all"}),f};"undefined"!=typeof module?module.exports=b:a.loadCSS=b}("undefined"!=typeof global?global:this);
        loadCSS('{{ elixir('css/app.css') }}');
    </script>

@else

    <link href="{{ elixir('css/app.css') }}" rel="stylesheet" type="text/css" />

@endif
