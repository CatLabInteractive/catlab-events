{{-- Needs $logoUrl (may be empty), $name and $accentColor. The logo, or the name when there is none. --}}
@if ($logoUrl)
<img src="{{ $logoUrl }}" width="220" alt="{{ $name }}" style="display:block;border:0;margin:0 auto;width:220px;max-width:100%;height:auto">
@else
<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:bold;color:{{ $accentColor }}">{{ $name }}</span>
@endif
