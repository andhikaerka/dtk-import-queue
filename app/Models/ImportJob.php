<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal.
     */
    protected $fillable = [
        'filename',
        'status',
        'total',
        'success',
        'failed',
    ];

    /**
     * Cast properti integer agar otomatis terkonversi dari database.
     */
    protected $casts = [
        'total' => 'integer',
        'success' => 'integer',
        'failed' => 'integer',
    ];
}