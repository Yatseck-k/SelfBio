<?php

declare(strict_types=1);

namespace Whodoku\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PuzzleHintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'level' => ['required', 'integer', 'min:1', 'max:3'],
            'placements' => ['sometimes', 'array'],
            'placements.*' => ['array', 'size:2'],
            'placements.*.0' => ['required', 'integer'],
            'placements.*.1' => ['required', 'integer'],
        ];
    }
}
