<?php

declare(strict_types=1);

namespace Whodoku\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPuzzleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // An empty board is a valid (incomplete) answer; `required` would reject `{}` as missing.
            'placements' => ['present', 'array'],
            'placements.*' => ['array', 'size:2'],
            'placements.*.0' => ['required', 'integer'],
            'placements.*.1' => ['required', 'integer'],
        ];
    }
}
