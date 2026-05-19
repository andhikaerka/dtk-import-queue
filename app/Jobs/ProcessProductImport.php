<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    protected $importJob;

    /**
     * 1. Menerima data model ImportJob dari Controller
     */
    public function __construct(ImportJob $importJob)
    {
        $this->importJob = $importJob;
    }

    /**
     * 2. Logika Utama Pemrosesan Data CSV
     */
    public function handle(): void
    {
        // Ubah status di database menjadi 'in_progress'
        $this->importJob->update(['status' => 'in_progress']);
        Log::info("INFO: Memulai pemrosesan Impor Produk untuk Job ID: {$this->importJob->id}");

        // Dapatkan path lengkap file CSV di private storage
        $filePath = storage_path('app/private/' . $this->importJob->filename);

        if (!file_exists($filePath)) {
            $this->importJob->update(['status' => 'failed']);
            Log::error("ERROR: File tidak ditemukan di path: {$filePath}");
            return;
        }

        // Buka file CSV dengan mode read-only 'r' (Streaming method agar hemat RAM)
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            // Ambil baris pertama sebagai header (name, sku, price, stock)
            $header = fgetcsv($handle, 1000, ','); 

            $successCount = 0;
            $failedCount = 0;
            $totalCount = 0;

            // Looping membaca file CSV baris demi baris
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $totalCount++;
                
                try {
                    // Gabungkan header dengan data baris menjadi array asosiatif
                    $row = array_combine($header, $data);

                    if (empty($row['sku']) || empty($row['name'])) {
                        throw new \Exception("Kolom SKU atau Nama tidak boleh kosong.");
                    }

                    // Gunakan updateOrCreate (Upsert) agar jika SKU sudah ada, datanya diupdate. 
                    // Jika belum ada, data akan dibuat baru.
                    Product::updateOrCreate(
                        ['sku' => $row['sku']],
                        [
                            'name' => $row['name'],
                            'price' => (float) $row['price'],
                            'stock' => (int) $row['stock'],
                        ]
                    );

                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning("WARN: Job ID {$this->importJob->id} - Gagal memproses baris ke-{$totalCount}. Error: " . $e->getMessage());
                }

                // Chunking/Reporting: Update progress ke DB setiap kelipatan 500 baris 
                // agar Postman status tidak stuck melainkan terlihat naik terus angkanya
                if ($totalCount % 500 === 0) {
                    $this->importJob->update([
                        'total' => $totalCount,
                        'success' => $successCount,
                        'failed' => $failedCount
                    ]);
                }
            }

            fclose($handle);

            // Selesai sepenuhnya, ubah status menjadi 'completed'
            $this->importJob->update([
                'status' => 'completed',
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => $failedCount
            ]);

            Log::info("INFO: Job ID {$this->importJob->id} Selesai diproses. Total: {$totalCount}, Sukses: {$successCount}, Gagal: {$failedCount}");
            
            // Hapus file CSV fisik setelah selesai diproses agar storage Docker tidak penuh
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}