<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class CsvDummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Inisialisasi Faker dengan bahasa Indonesia agar nama/kata terasa lokal (opsional)
        $faker = Faker::create('id_ID');

        // Tentukan lokasi file CSV
        $filePath = storage_path('app/private/products_real_sample.csv');

        // Pastikan direktorinya ada
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');

        // Tulis header sesuai format di soal
        fputcsv($file, ['name', 'sku', 'price', 'stock']);

        // Gunakan array penampung SKU untuk memastikan tidak ada SKU yang duplikat (Unique)
        $generatedSkus = [];
        
        $totalRows = 100000; // Target 100.000 data

        $this->command->info("Sedang men-generate {$totalRows} data CSV menggunakan Faker...");

        for ($i = 0; $i < $totalRows; $i++) {
            
            // 2. Membuat nama produk nyata menggunakan Faker
            // Menggabungkan 2-3 kata acak, lalu buat huruf kapital di awal kata (Title Case)
            $words = $faker->words(rand(2, 3));
            $productName = ucwords(implode(' ', $words));

            // 3. Membuat SKU unik menggunakan Faker atau string acak
            do {
                // Mengambil 3 huruf acak + angka acak untuk format SKU profesional
                $prefix = strtoupper($faker->lexify('???'));
                $code = $faker->numerify('#####');
                $sku = "SKU-{$prefix}-{$code}"; // Contoh: SKU-ABC-12345
            } while (isset($generatedSkus[$sku])); // Cek efisien menggunakan key array

            $generatedSkus[$sku] = true;

            // 4. Membuat harga dan stok yang realistis
            $price = $faker->numberBetween(10, 500) * 5000; // Rentang harga Rp 50.000 s.d Rp 2.500.000
            $stock = $faker->numberBetween(0, 500);         // Stok antara 0 s.d 500

            // Tulis baris ke file CSV
            fputcsv($file, [$productName, $sku, $price, $stock]);

            // Tampilkan progress setiap 5.000 baris agar terminal tidak terlihat hang
            if (($i + 1) % 5000 === 0) {
                $this->command->line("-> Berhasil membuat " . ($i + 1) . " baris...");
            }
        }

        fclose($file);

        $this->command->info("Sukses! File CSV dengan $totalRows data Faker berhasil dibuat di: {$filePath}");
    }
}