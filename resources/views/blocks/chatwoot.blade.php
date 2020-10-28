@if($organisation && $organisation->chatwoot_url)
    <script>
        (function(d,t) {
            var BASE_URL = "{{$organisation->chatwoot_url}}";
            var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
            g.src= BASE_URL + "/packs/js/sdk.js";
            s.parentNode.insertBefore(g,s);
            g.onload=function(){
                @if(isset($livestream) && $livestream && $organisation->chatwoot_livestream_token)
                    var websiteToken = '{{$organisation->chatwoot_livestream_token}}';
                @else
                    var websiteToken = '{{$organisation->chatwoot_token}}';
                @endif

                window.chatwootSettings = {
                    locale: '{{ \App::getLocale() }}'
                };

                window.chatwootSDK.run({
                    websiteToken: websiteToken,
                    baseUrl: BASE_URL
                })
            }
        })(document,"script");
    </script>
@endif
