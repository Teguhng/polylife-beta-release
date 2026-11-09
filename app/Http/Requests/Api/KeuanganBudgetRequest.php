<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class KeuanganBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kategori' => ['required', 'string', 'max:100'],
            'nominal_limit' => ['required', 'numeric', 'min:1000'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.required' => 'Kategori anggaran harus diisi.',
            'nominal_limit.required' => 'Nominal anggaran harus diisi.',
            'nominal_limit.min' => 'Nominal anggaran minimal Rp 1.000.',
            'bulan.between' => 'Bulan tidak valid.',
            'tahun.required' => 'Tahun anggaran harus diisi.',
        ];
    }
}