{{-- Tabs of the E-mails settings. $active is 'standard' or 'library'. --}}
<ul class="nav nav-tabs" style="margin-bottom: 20px;">
    <li class="nav-item">
        <a class="nav-link @if($active === 'standard') active @endif"
           href="{{ action('Admin\EmailTemplateController@index') }}">Standaardmails</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($active === 'library') active @endif"
           href="{{ action('Admin\EmailLibraryController@index') }}">Sjablonen</a>
    </li>
</ul>
