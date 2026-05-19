<?php

namespace Tests\Feature;

use App\Enums\ImportJobStatus;
use App\Jobs\ProcessProductImport;
use App\Models\ImportJob;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessProductImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_successfully_processes_csv_file()
    {
        Storage::fake('local');
        
        $csvContent = "name,sku,price,stock\nProduct A,SKU001,10000,50\nProduct B,SKU002,20000,10\n";
        
        Storage::disk('local')->put('imports/test.csv', $csvContent);

        $importJob = ImportJob::create([
            'filename' => 'imports/test.csv',
            'status' => ImportJobStatus::PENDING->value,
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ]);

        $job = new ProcessProductImport($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(ImportJobStatus::COMPLETED->value, $importJob->status);
        $this->assertEquals(2, $importJob->total);
        $this->assertEquals(2, $importJob->success);
        $this->assertEquals(0, $importJob->failed);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU001',
            'name' => 'Product A',
            'price' => 10000,
            'stock' => 50,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU002',
            'name' => 'Product B',
            'price' => 20000,
            'stock' => 10,
        ]);
        
        // Assert file deleted
        Storage::disk('local')->assertMissing('imports/test.csv');
    }

    public function test_job_handles_missing_file()
    {
        Storage::fake('local'); // ensure fake disk so it doesn't look at real missing files either

        $importJob = ImportJob::create([
            'filename' => 'imports/missing.csv',
            'status' => ImportJobStatus::PENDING->value,
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ]);

        $job = new ProcessProductImport($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(ImportJobStatus::FAILED->value, $importJob->status);
    }
    
    public function test_job_handles_invalid_data()
    {
        Storage::fake('local');
        
        // Invalid data: second row is missing sku
        $csvContent = "name,sku,price,stock\nProduct A,,10000,50\n";
        
        Storage::disk('local')->put('imports/invalid.csv', $csvContent);

        $importJob = ImportJob::create([
            'filename' => 'imports/invalid.csv',
            'status' => ImportJobStatus::PENDING->value,
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ]);

        $job = new ProcessProductImport($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(ImportJobStatus::COMPLETED->value, $importJob->status);
        $this->assertEquals(1, $importJob->total);
        $this->assertEquals(0, $importJob->success);
        $this->assertEquals(1, $importJob->failed); // Because SKU is missing, it skips it
    }

    public function test_job_updates_existing_product_if_sku_matches()
    {
        Storage::fake('local');

        // First insert a product
        Product::create([
            'sku' => 'SKU003',
            'name' => 'Old Name',
            'price' => 5000,
            'stock' => 5,
        ]);

        $csvContent = "name,sku,price,stock\nNew Name,SKU003,15000,100\n";
        Storage::disk('local')->put('imports/update.csv', $csvContent);

        $importJob = ImportJob::create([
            'filename' => 'imports/update.csv',
            'status' => ImportJobStatus::PENDING->value,
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ]);

        $job = new ProcessProductImport($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(ImportJobStatus::COMPLETED->value, $importJob->status);
        
        // Assert that the product was updated
        $this->assertDatabaseHas('products', [
            'sku' => 'SKU003',
            'name' => 'New Name',
            'price' => 15000,
            'stock' => 100,
        ]);
        
        // Assert we still only have 1 product (updateOrCreate used)
        $this->assertEquals(1, Product::count());
    }
}
