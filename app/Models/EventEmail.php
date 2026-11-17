<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EventEmail
 *
 * The mail of one type for one event. It either points to a library template
 * (email_template_id) or holds its own subject and content.
 *
 * @property int $event_id
 * @property string $type
 * @property int|null $email_template_id
 * @property string|null $subject
 * @property string|null $content HTML, without the mail layout
 *
 * @package App\Models
 */
class EventEmail extends Model
{
    protected $fillable = [ 'type', 'email_template_id', 'subject', 'content' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function emailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * @return bool
     */
    public function hasOwnText()
    {
        return $this->subject !== null;
    }
}
