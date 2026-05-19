<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductImport\UploadRequest;
use App\Http\Resources\Api\ImportJobResource;
use App\Models\ImportJob;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;
use Exception;

class ProductImportController extends Controller
{
    public function __construct(private ProductImportService $importService)
    {
    }

    /**
     * Endpoint 1: Upload File CSV (Dilempar ke Redis Queue)
     * POST /api/import/products
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        try {
            $importJob = $this->importService->handleCsvUpload($request->file('file'));

            return response()->json([
                'job_id' => $importJob->id,
                'status' => $importJob->status,
            ], 202);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses file upload.',
            ], 500);
        }
    }

    /**
     * Endpoint 2: Cek Progress Status Jalannya Antrian
     * GET /api/import/status/{id}
     */
    public function status(string $id): ImportJobResource
    {
        $importJob = ImportJob::findOrFail($id);

        return new ImportJobResource($importJob);
    }
}