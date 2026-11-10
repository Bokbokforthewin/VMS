<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    // Which document types each owner must have (checked when booking).
    public const REQUIRED = [
        'vehicle' => ['registration', 'insurance'],
        'driver' => ['license'],
    ];

    protected $fillable = ['doc_type', 'owner_id', 'type', 'renewal', 'expires_at', 'file_path'];

    protected function casts(): array
    {
        return [
            'renewal' => 'boolean',
            'expires_at' => 'date',
        ];
    }
}