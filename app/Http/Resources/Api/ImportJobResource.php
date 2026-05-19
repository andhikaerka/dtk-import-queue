<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'success' => $this->success,
            'failed' => $this->failed,
            'updated_at' => $this->updated_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d H:i:s'),
        ];
    }
}
