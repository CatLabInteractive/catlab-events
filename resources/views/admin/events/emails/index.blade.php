@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>E-mails</h3>

    <p>
        Zonder aanpassing verstuurt dit evenement de standaardversie van de organisatie.
        Je kan per mail een sjabloon kiezen of een eigen tekst schrijven voor dit evenement.
    </p>

    @if(count($errors) > 0)
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Mail</th>
                <th>Versie</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($mails as $type => $mail)
                <tr>
                    <td>
                        <strong>{{ $mail['label'] }}</strong><br />
                        <small class="text-muted">{{ $mail['description'] }}</small>
                        @if(!$mail['isApplicable'])
                            <br /><small class="text-danger">Dit evenement verstuurt deze mail niet. Je kan de aanpassing verwijderen.</small>
                        @endif
                    </td>
                    <td>{{ $mail['origin'] }}</td>
                    <td class="text-right" style="white-space: nowrap;">
                        <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener"
                           href="{{ action('Admin\EventEmailController@preview', [ $event->id, $type ]) }}">Bekijk</a>

                        @if($mail['isApplicable'])
                            <a class="btn btn-sm btn-primary" href="{{ action('Admin\EventEmailController@edit', [ $event->id, $type ]) }}">
                                @if($mail['kind'] === \App\Services\MailSource::EVENT)
                                    Eigen tekst bewerken
                                @else
                                    Eigen tekst schrijven
                                @endif
                            </a>

                            @if(count($mail['libraryTemplates']) > 0)
                                <form action="{{ action('Admin\EventEmailController@template', [ $event->id, $type ]) }}" method="post"
                                      style="display: inline-block; margin-left: 5px;"
                                      @if($mail['kind'] === \App\Services\MailSource::EVENT)
                                          onsubmit="return confirm('De eigen tekst van dit evenement wordt vervangen door het sjabloon. Doorgaan?');"
                                      @endif>
                                    {{ csrf_field() }}
                                    <select class="form-control form-control-sm" name="email_template_id"
                                            style="display: inline-block; width: auto;" aria-label="Sjabloon">
                                        @foreach($mail['libraryTemplates'] as $libraryTemplate)
                                            <option value="{{ $libraryTemplate->id }}"
                                                    @if($mail['templateId'] === $libraryTemplate->id) selected @endif>
                                                {{ $libraryTemplate->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Sjabloon gebruiken</button>
                                </form>
                            @endif
                        @endif

                        @if($mail['hasChoice'])
                            <form action="{{ action('Admin\EventEmailController@reset', [ $event->id, $type ]) }}" method="post"
                                  style="display: inline-block; margin-left: 5px;"
                                  onsubmit="return confirm('@if($mail['kind'] === \App\Services\MailSource::EVENT)De eigen tekst van dit evenement gaat verloren. @endif Dit evenement verstuurt weer de standaard van de organisatie. Doorgaan?');">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-sm btn-danger">Terug naar standaard</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
