<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Jobs\ProcessProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductImportController extends Controller
{
    /**
     * Endpoint 1: Upload File CSV (Dilempar ke Redis Queue)
     * POST /api/import/products
     */
    public function upload(Request $request)
    {
        // 1. Validasi ketat format file CSV (maksimal 20MB untuk menampung puluhan ribu baris)
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Simpan file CSV ke dalam storage private ('storage/app/private/imports')
        $path = $request->file('file')->store('imports');

        // 3. Catat job baru ke PostgreSQL dengan status 'pending'
        $importJob = ImportJob::create([
            'filename' => $path,
            'status' => 'pending',
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ]);

        // 4. KIRIM KE REDIS QUEUE
        // Fungsi dispatch() ini akan langsung melempar tugas ke Redis.
        // Server akan langsung mengembalikan respon ke Postman TANPA menunggu 30.000 data selesai diproses.
        ProcessProductImport::dispatch($importJob);

        // 5. Kembalikan respons sukses instan (HTTP 202 Accepted)
        return response()->json([
            'job_id' => $importJob->id,
            'status' => $importJob->status,
        ], 202);
    }

    /**
     * Endpoint 2: Cek Progress Status Jalannya Antrian
     * GET /api/import/status/{id}
     */
    public function status($id)
    {
        // Cari job berdasarkan ID, jika tidak ada otomatis return 404
        $importJob = ImportJob::findOrFail($id);

        return response()->json([
            'job_id'     => $importJob->id,
            'status'     => $importJob->status, // pending, in_progress, completed, atau failed
            'total'      => $importJob->total,
            'success'    => $importJob->success,
            'failed'     => $importJob->failed,
            'updated_at' => $importJob->updated_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ], 200);
    }
}