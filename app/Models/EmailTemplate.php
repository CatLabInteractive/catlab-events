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
 * @property string $subject
 * @property string $content HTML, without the mail layout
 *
 * @package App\Models
 */
class EmailTemplate extends Model
{
    protected $fillable = [ 'type', 'subject', 'content' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}
