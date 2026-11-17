@if(config('services.matomo.url') && config('services.matomo.site_id'))
<!-- Matomo -->
<script>
    var _paq = window._paq = window._paq || [];
    // Cookieless: no consent needed. Must come before trackPageView.
    _paq.push(['disableCookies']);
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    _paq.push(['enableHeartBeatTimer', 15]);
    (function() {
        var u = @json(rtrim(config('services.matomo.url'), '/') . '/');
        _paq.push(['setTrackerUrl', u + 'matomo.php']);
        _paq.push(['setSiteId', @json((string) config('services.matomo.site_id'))]);
        var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
        g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
    })();
</script>
<!-- End Matomo -->
@endif
