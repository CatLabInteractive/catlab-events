<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EmailTemplate
 *
 * An organisation's own version of one of the transactional mails. The
 * subject and content hold {{ placeholders }} that are filled in when the
 * mail goes out; see App\Services\EmailTemplates for the types and the
 * placeholders each one knows.
 *
 * @property int $organisation_id
 * @property string $type
 * @property string $name empty for the organisation's version, set for a library template
 * @property string $subject
 * @property string $content HTML, without the mail layout
 *
 * @package App\Models
 */
class EmailTemplate extends Model
{
    protected $fillable = [ 'type', 'name', 'subject', 'content' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventEmails()
    {
        return $this->hasMany(EventEmail::class);
    }

    /**
     * The version that goes out for the whole organisation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrganisationVersion($query)
    {
        return $query->where('name', '=', '');
    }

    /**
     * Named templates an admin can pick for an event.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLibrary($query)
    {
        return $query->where('name', '<>', '');
    }
}
