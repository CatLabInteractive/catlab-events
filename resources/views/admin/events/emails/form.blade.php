{{-- The Charon form of one mail of an event. --}}
@extends('charonfrontend::crud.form')

@section('cfcontent')

    @php($mail = $resource ? $resource->getSource() : null)

    <p class="alert alert-info">
        @if($mail && $mail->hasOwnText())
            Dit evenement verstuurt deze eigen tekst. Wijzigingen gelden alleen voor dit evenement.
        @elseif($mail && $mail->email_template_id)
            Dit evenement verstuurt een sjabloon. Kies een ander sjabloon, of kies "Eigen tekst" om de tekst hieronder te versturen.
        @else
            Dit evenement heeft nog geen eigen keuze voor deze mail. De tekst hieronder is de versie die het nu verstuurt.
            Wanneer je opslaat, krijgt dit evenement een sjabloon of een eigen tekst. De standaard van de organisatie verandert niet.
        @endif
    </p>

    @parent

@endsection
