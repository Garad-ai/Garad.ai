<?php

namespace App\Extensions\MarketingBot\System\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactListContact extends Pivot
{
    protected $table = 'ext_contact_list_contact';

    protected $fillable = [
        'contact_list_id',
        'contact_id',
    ];
}
