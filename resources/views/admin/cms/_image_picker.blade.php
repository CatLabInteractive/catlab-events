{{-- Image picker (cms-editor.js): the organisation's images, search and upload. --}}
<div class="modal fade" id="cms-image-picker" tabindex="-1" role="dialog" aria-labelledby="cms-image-picker-title" aria-hidden="true" data-cms-image-picker>
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cms-image-picker-title">Kies een afbeelding</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Sluiten"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row mb-3">
                    <div class="col-sm-7 mb-2 mb-sm-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Zoek op bestandsnaam" aria-label="Zoek op bestandsnaam" data-cms-picker-search />
                    </div>
                    <div class="col-sm-5">
                        <label class="btn btn-sm btn-outline-primary mb-0 w-100">
                            Nieuwe afbeelding uploaden
                            <input type="file" accept="image/*" class="d-none" data-cms-picker-upload />
                        </label>
                    </div>
                </div>
                <div class="alert alert-danger d-none" role="alert" data-cms-picker-error></div>
                <p class="text-muted small d-none" data-cms-picker-status></p>
                <div class="cms-picker-grid" data-cms-picker-grid></div>
            </div>
        </div>
    </div>
</div>
