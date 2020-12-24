@extends('emails/layouts/layout')

@section('content')

    <h2>Dank voor je aankoop!</h2>

    <p>Hallo</p>
    <p>Je kocht Quizfabrieks Winterquiz. Je kan die spelen via volgende link:
        {{ $event->getIdentifiedLiveStreamUrl($group) }}<p></p>
    <p>Als je de link volgt, druk je op 'Start' wanneer je klaar bent om te beginnen. Selecteer 'Party Spel' om met meer dan 1 persoon te spelen. Op dat moment krijg je instructies hoe met het spel te verbinden: ieder team surft met hun tablet of smartphone naar www.quizwitz.tv en geeft de zescijferige code in die je op het scherm ziet staan. Zodra iedereen verbonden is, druk je op 'Start' en kan het spel beginnen. Je kan spelen zonder in te loggen op je tablet of smartphone, maar dan kan je wel geen teamnaam kiezen. Let op: het inladen van het spel kan een tijdje duren.</p>
    <p>Als je over bubbels heen wilt spelen, kan je een online call opzetten met je familie of vrienden (via Zoom, Hangouts, Jitsi Meet, …). Daarna surf je in een ander venster naar de link. Hier krijg je dus de quiz te zien. Je deelt het venster met de quiz in de online call, zodat iedereen het te zien krijgt.</p>
    <p>De link blijft geldig tot 28 februari 2021.</p>
    <p>Als je vragen of opmerkingen hebt, je kan altijd terecht bij hallo@quizfabriek.be.</p>
    <p>Veel quizplezier en prettige feesten gewenst!</p>

    <p>
        De Quizfabriek
    </p>

@endsection
