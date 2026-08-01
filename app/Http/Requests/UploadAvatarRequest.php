<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('authzio.assets.max_kilobytes', 2048);
        /** @var list<string> $mimes */
        $mimes = config('authzio.assets.mimes', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'max:'.$maxKb,
                'mimes:'.implode(',', $mimes),
            ],
        ];
    }
}
