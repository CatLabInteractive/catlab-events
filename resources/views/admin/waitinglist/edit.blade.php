@extends('layouts/admin')

@section('content')

    <h2>{{ $event->name }}</h2>
    <h3>Uitnodiging aanpassen</h3>

    <p class="alert alert-info">
        Deze uitnodiging is nog niet verstuurd. De persoonlijke link hieronder werkt al:
        pas de tekst aan en verstuur hem hier, of kopieer hem naar je eigen mailprogramma.
    </p>

    @if($user->pivot->invitation_sent_at)
        <p class="alert alert-warning">
            Deze persoon kreeg al een uitnodiging op
            {{ \Carbon\Carbon::parse($user->pivot->invitation_sent_at)->format('d/m/Y H:i') }}.
        </p>
    @endif

    <form action="{{ action('Admin\WaitingListController@sendInvite', [ $event->id, $user->id ]) }}" method="post"
          id="waitinglist-edit-form">
        {{ csrf_field() }}

        <table class="table">
            <tr>
                <th style="width: 120px;">Aan</th>
                <td>{{ $user->username }} &lt;{{ $user->email }}&gt;</td>
            </tr>
            <tr>
                <th><label for="subject">Onderwerp</label></th>
                <td>
                    <input type="text" class="form-control" id="subject" name="subject"
                           value="{{ old('subject', $subject) }}" maxlength="255" required />
                </td>
            </tr>
            <tr>
                <th>Link</th>
                <td><code>{{ $url }}</code></td>
            </tr>
        </table>

        {{-- The editor holds admin-written HTML; the textarea is what gets posted. --}}
        <div id="waitinglist-editor" contenteditable="true"
             style="border: 1px solid #ddd; padding: 15px; min-height: 300px; background: #fff;">{!! old('content', $content) !!}</div>
        <textarea name="content" id="waitinglist-content" style="display: none;">{{ old('content', $content) }}</textarea>

        <p style="margin-top: 15px;">
            <button type="submit" class="btn btn-success">Verstuur deze versie</button>
            <button type="button" class="btn btn-primary" id="waitinglist-copy">Kopieer tekst</button>
            <a class="btn btn-secondary" href="{{ action('Admin\WaitingListController@index', [ $event->id ]) }}">Terug</a>
        </p>
    </form>

    <script>
        (function () {
            var editor = document.getElementById('waitinglist-editor');
            var content = document.getElementById('waitinglist-content');
            var copy = document.getElementById('waitinglist-copy');

            document.getElementById('waitinglist-edit-form').addEventListener('submit', function () {
                content.value = editor.innerHTML;
            });

            copy.addEventListener('click', function () {
                var done = function () {
                    copy.textContent = 'Gekopieerd';
                    setTimeout(function () { copy.textContent = 'Kopieer tekst'; }, 2000);
                };

                // Keep the formatting (and the link) when pasting into a mail client.
                if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
                    navigator.clipboard.write([
                        new ClipboardItem({
                            'text/html': new Blob([ editor.innerHTML ], { type: 'text/html' }),
                            'text/plain': new Blob([ editor.innerText ], { type: 'text/plain' })
                        })
                    ]).then(done);
                    return;
                }

                var range = document.createRange();
                range.selectNodeContents(editor);
                var selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                document.execCommand('copy');
                selection.removeAllRanges();
                done();
            });
        })();
    </script>

@endsection
