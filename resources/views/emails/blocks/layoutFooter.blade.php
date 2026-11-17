{{--
    Needs $footerText (may be empty) and $lines: the contact line and the
    social line, each a list of [ label, url|null ], only with what the
    organisation filled in. An empty line is not passed at all.
--}}
@if ($footerText !== '')
<p style="margin:0 0 14px;font-size:13px;line-height:1.5;color:#3d4451">{!! nl2br(e($footerText)) !!}</p>
@endif
@foreach (array_values($lines) as $index => $line)
<p style="margin:{{ $index < count($lines) - 1 ? '0 0 6px' : '0' }}">@foreach ($line as $position => $item){!! $position > 0 ? ' &middot; ' : '' !!}{!! $item['url'] ? '<a href="' . e($item['url']) . '" style="color:#5d6676;text-decoration:underline">' . e($item['label']) . '</a>' : e($item['label']) !!}@endforeach</p>
@endforeach
