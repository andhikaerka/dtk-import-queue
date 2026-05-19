<?php

namespace Tests\Unit;

use App\Enums\ImportJobStatus;
use App\Jobs\ProcessProductImport;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importService = new ProductImportService();
    }

    public function test_handle_csv_upload_stores_file_and_dispatches_job()
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('products.csv', 100, 'text/csv');

        $importJob = $this->importService->handleCsvUpload($file);

        // Assert file tersimpan
        Storage::assertExists($importJob->filename);

        // Assert data job ada di database
        $this->assertDatabaseHas('import_jobs', [
            'id' => $importJob->id,
            'status' => ImportJobStatus::PENDING->value,
            'total' => 0,
        ]);

        // Assert job masuk antrian
        Queue::assertPushed(ProcessProductImport::class, function ($job) use ($importJob) {
            return $job->importJob->id === $importJob->id;
        });
    }
}
