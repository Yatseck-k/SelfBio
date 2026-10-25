<?php

declare(strict_types=1);

namespace Whodoku\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Whodoku\Models\Incident;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Incident::CATEGORIES))],
            'message' => ['required', 'string', 'min:5', 'max:1000'],
            'placements' => ['sometimes', 'array', 'max:12'],
            'placements.*' => ['array', 'size:2'],
            'placements.*.0' => ['required', 'integer'],
            'placements.*.1' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.required' => 'Выберите, что не так.',
            'message.required' => 'Опишите проблему в паре слов.',
            'message.min' => 'Опишите проблему чуть подробнее (не меньше 5 символов).',
            'message.max' => 'Сообщение слишком длинное: уложитесь в 1000 символов.',
        ];
    }
}
