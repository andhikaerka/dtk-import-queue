<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal (Mass Assignment).
     * Sesuai dengan spesifikasi file CSV di soal.
     */
    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
    ];

    /**
     * Casting tipe data agar sesuai saat diakses di aplikasi.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];
}