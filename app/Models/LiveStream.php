<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Support\Str;

/**
 * Class LiveStream
 * @package App\Models
 */
class LiveStream extends Model
{
    protected $table = 'livestreams';

    public static function boot()
    {
        parent::boot();

        self::creating(function(LiveStream $model) {

            $tries = 10;
            while ($tries > 0) {
                $tries --;

                $model->token = Str::lower(Str::random(16));

                // check for duplicate
                if (!LiveStream::where('token', '=', $model->token)->exists()) {
                    return;
                }

                throw new \LogicException('Failed generating a unique key.');
            }

        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return string
     */
    public function getLivestreamUrl()
    {
        $organisation = $this->organisation;

        if (count($organisation->domains) > 0) {
            $domain = $organisation->domains->first()->domain;
        } else {
            $domain = \Request::getHttpHost();
            if (Str::startsWith($domain, 'live.')) {
                $domain = 'live.' . $domain;
            }
        }

        return (\Request::secure() ? 'https' : 'http' ) . '://live.' . $domain . '/' . $this->token;
    }
}
