{{-- $text, optional $level (defaults to 2) and $class --}}
@if(isset($text) && $text !== '' && $text !== null)
    <?php $level = isset($level) && in_array($level, [ 1, 2, 3 ], true) ? $level : 2; ?>
    <h{{ $level }} class="{{ $class ?? 'cms-block-title' }}">{{ $text }}</h{{ $level }}>
@endif
