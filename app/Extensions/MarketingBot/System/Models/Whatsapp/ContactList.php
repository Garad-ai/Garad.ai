<?php

namespace App\Extensions\MarketingBot\System\Models\Whatsapp;

use Database\Factories\ContactListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    use HasFactory;

    protected static function newFactory(): ContactListFactory
    {
        return ContactListFactory::new();
    }

    protected $table = 'ext_contact_lists';

    protected $fillable = [
        'user_id',
        'country_code',
        'name',
        'last_name',
        'phone',
        'avatar',
    ];

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'ext_contact_list_segment', 'contact_list_id', 'segment_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'ext_contact_list_contact', 'contact_list_id', 'contact_id');
    }
}
