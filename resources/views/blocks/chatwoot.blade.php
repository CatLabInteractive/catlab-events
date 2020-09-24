@if(organisation()->chatwoot_url)
    <script>
        (function(d,t) {
            var BASE_URL = "{{organisation()->chatwoot_url}}";
            var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
            g.src= BASE_URL + "/packs/js/sdk.js";
            s.parentNode.insertBefore(g,s);
            g.onload=function(){
                window.chatwootSDK.run({
                    websiteToken: '{{organisation()->chatwoot_token}}',
                    baseUrl: BASE_URL
                })
            }
        })(document,"script");
    </script>
@endif
