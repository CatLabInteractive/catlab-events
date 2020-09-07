<div class="newsletter-subscribe">
    <p>
        <a href="{{ action('CatLabAccountController@redirect', [ 'path' => 'myaccount', 'return' => \Request::url() ]) }}" class="btn btn-success">
            @if(isset($text))
                {{$text}}
            @else
                Hou me op de hoogte
            @endif
        </a>
    </p>
</div>