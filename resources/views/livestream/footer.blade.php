
@if(!isset($hideSupport) || !$hideSupport)
    <p>
    @if($organisation->helpdesk_url)
        Heb je vragen of problemen? Surf naar <a href="{{$organisation->helpdesk_url}}" target="_blank">{{ parse_url($organisation->helpdesk_url)['host'] }}</a>.<br />
    @endif

    @if(count($organisation->getSocialLinks()) > 0)
        {!! $organisation->getSocialLinksText() !!}
    @endif
    </p>
@endif
