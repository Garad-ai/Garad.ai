<?php

namespace App\Extensions\MarketingBot\System\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactListSegment extends Pivot
{
    protected $table = 'ext_contact_list_segment';

    protected $fillable = [
        'contact_list_id',
        'segment_id',
    ];
}
