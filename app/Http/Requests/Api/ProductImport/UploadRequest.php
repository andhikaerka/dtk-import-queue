<?php

namespace App\Http\Requests\Api\ProductImport;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('app.import_max_size', 20480);
        
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:' . $maxSize],
        ];
    }
}
