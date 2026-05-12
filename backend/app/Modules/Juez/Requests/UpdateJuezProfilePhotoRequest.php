<?php

namespace App\Modules\Juez\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJuezProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'La foto es obligatoria.',
            'photo.image' => 'El archivo debe ser una imagen.',
            'photo.mimes' => 'La foto debe estar en formato JPG, JPEG, PNG o WEBP.',
            'photo.max' => 'La foto no debe superar los 5 MB.',
        ];
    }
}