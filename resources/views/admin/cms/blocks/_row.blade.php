<div class="cms-repeater-row border rounded p-2 mb-2">
    <div class="d-flex justify-content-end mb-1">
        <button type="button" class="btn btn-sm btn-link py-0" data-cms-action="up" title="Omhoog">&uarr;</button>
        <button type="button" class="btn btn-sm btn-link py-0" data-cms-action="down" title="Omlaag">&darr;</button>
        <button type="button" class="btn btn-sm btn-link text-danger py-0" data-cms-action="remove" title="Verwijder">&times;</button>
    </div>
    @include($rowView, [ 'rowName' => $rowName, 'rowError' => $rowError, 'row' => $row ])
</div>
