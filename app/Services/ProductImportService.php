<?php

namespace App\Services;

use App\Enums\ImportJobStatus;
use App\Models\ImportJob;
use App\Jobs\ProcessProductImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductImportService
{
    public function handleCsvUpload(UploadedFile $file): ImportJob
    {
        try {
            $path = $file->store('imports');
            
            if (!$path) {
                throw new Exception('Gagal menyimpan file CSV ke storage.');
            }

            $importJob = ImportJob::create([
                'filename' => $path,
                'status' => ImportJobStatus::PENDING->value,
                'total' => 0,
                'success' => 0,
                'failed' => 0,
            ]);

            ProcessProductImport::dispatch($importJob);

            return $importJob;
            
        } catch (Exception $e) {
            Log::error('Error saat upload CSV Import: ' . $e->getMessage());
            throw $e;
        }
    }
}
