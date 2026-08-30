@extends('layouts/front')

@section('title')
    {{ $group->name }} Samenvoegen
@endsection

@section('content')

    <h2>{{ $group->name }} samenvoegen</h2>
    <p>
        Door twee teams samen te voegen worden de scores van beide groepen gedeeld. Dat kan nodig zijn als je
        bijvoorbeeld per ongeluk twee groepen hebt aangemaakt. Om van deze functie gebruik te maken moet
        je administrator van beide teams zijn. Ben je dat niet? Vraag dan aan een administrator van de andere
        groep om jou eerst uit te nodigen.
    </p>

    <h3>Samenvoegen</h3>
    @if(count($otherGroups) === 0)

        <p>Je behoort niet tot andere teams, dus kan je geen teams samenvoegen.</p>

    @else

        <form method="POST" action="{{ action('GroupController@mergeGroup', $group->id) }}" accept-charset="UTF-8">
        @csrf
        <p>Kies het team waarmee je {{ $group->name }} wilt samenvoegen.</p>

        <div class="form-group">
            <label for="id">{{ $group->name . ' samenvoegen met' }}</label>
            <select name="id" id="id" class="form-control">
                @foreach($otherGroups as $value => $label)
                    <option value="{{ $value }}" @selected((string)old('id') === (string)$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <input type="submit" value="Samenvoegen" class="btn btn-primary">

        </form>

    @endif

@endsection