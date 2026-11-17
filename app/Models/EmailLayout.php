<?php
/**
 * CatLab Events - Event ticketing system
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EmailLayout
 *
 * An organisation's changes to the layout around every mail.
 *
 * @property int $organisation_id
 * @property string|null $accent_color
 * @property string|null $footer_text
 * @property string|null $custom_html
 *
 * @package App\Models
 */
class EmailLayout extends Model
{
    protected $fillable = [ 'accent_color', 'footer_text', 'custom_html' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}
