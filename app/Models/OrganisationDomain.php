<?php


namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;

/**
 * Class OrganisationDomain
 * @package App\Models
 */
class OrganisationDomain extends Model
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}
