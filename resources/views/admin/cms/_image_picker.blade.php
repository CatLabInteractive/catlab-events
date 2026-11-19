{{-- Image picker (cms-editor.js): the organisation's images, search and upload.
     In "several" mode (a gallery) images are ticked and added together. --}}
<div class="modal fade" id="cms-image-picker" tabindex="-1" role="dialog" aria-labelledby="cms-image-picker-title" aria-hidden="true" data-cms-image-picker>
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cms-image-picker-title" data-cms-picker-title>Kies een afbeelding</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Sluiten"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row mb-3">
                    <div class="col-sm-7 mb-2 mb-sm-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Zoek op bestandsnaam" aria-label="Zoek op bestandsnaam" data-cms-picker-search />
                    </div>
                    <div class="col-sm-5">
                        <label class="btn btn-sm btn-outline-primary mb-0 w-100">
                            <span data-cms-picker-upload-label>Nieuwe afbeelding uploaden</span>
                            <input type="file" accept="image/*" class="d-none" data-cms-picker-upload />
                        </label>
                    </div>
                </div>
                <div class="alert alert-danger d-none" role="alert" data-cms-picker-error></div>
                <p class="text-muted small d-none" data-cms-picker-status></p>
                <div class="cms-picker-grid" data-cms-picker-grid></div>
            </div>
            <div class="modal-footer d-none" data-cms-picker-footer>
                <span class="text-muted small mr-auto" data-cms-picker-count></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-sm btn-primary" data-cms-picker-confirm disabled>Toevoegen</button>
            </div>
        </div>
    </div>
</div>
