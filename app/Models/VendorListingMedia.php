<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorListingMedia extends Model
{
    protected $fillable = [
        'vendor_listing_id',
        'path',
        'mime_type',
        'media_type',
        'position',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }
}
