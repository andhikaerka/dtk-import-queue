<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImport;
use App\Models\User;
use App\Models\ImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_upload_csv()
    {
        $response = $this->postJson('/api/import/products', []);
        $response->assertStatus(401);
    }

    public function test_authorized_user_can_upload_csv_and_dispatch_job()
    {
        // Memalsukan Queue agar tidak dieksekusi sungguhan saat testing
        Queue::fake();
        // Memalsukan storage agar tidak mengotori disk
        Storage::fake('local');

        $user = User::factory()->create();
        
        // Membuat simulasi dummy file CSV
        $file = UploadedFile::fake()->create('products.csv', 100, 'text/csv');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/import/products', [
            'file' => $file,
        ]);

        // Ekspektasi: Harus mengembalikan status 202 Accepted secara instan
        $response->assertStatus(202)
                 ->assertJsonStructure(['job_id', 'status']);

        // Ekspektasi: Data job berhasil dicatat di database
        $this->assertDatabaseHas('import_jobs', [
            'id' => $response->json('job_id'),
            'status' => 'pending'
        ]);

        // Ekspektasi: Job benar-benar dilempar ke antrian (Redis)
        Queue::assertPushed(ProcessProductImport::class);
    }

    public function test_user_can_check_job_status()
    {
        $user = User::factory()->create();
        
        // Simulasikan sebuah Job yang sedang in_progress
        $importJob = ImportJob::create([
            'filename' => 'imports/dummy.csv',
            'status' => 'in_progress',
            'total' => 100,
            'success' => 50,
            'failed' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/import/status/' . $importJob->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'job_id' => $importJob->id,
                     'status' => 'in_progress',
                     'total' => 100,
                     'success' => 50,
                 ]);
    }
}
