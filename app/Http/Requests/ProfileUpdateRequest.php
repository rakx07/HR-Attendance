<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id ?? null;

        return [
            // split name fields
            'first_name'       => ['required','string','max:100'],
            'middle_name'      => ['nullable','string','max:100'],
            'last_name'        => ['required','string','max:100'],

            // email uniqueness except current user
            'email'            => ['required','email', Rule::unique('users','email')->ignore($userId)],

            // optional profile fields you might expose
            'department'       => ['nullable','string','max:100'],
            'zkteco_user_id'   => ['nullable','string','max:64'],   // keep string to preserve leading zeros
            'shift_window_id'  => ['nullable','integer','exists:shift_windows,id'],
            'flexi_start'      => ['nullable','date_format:H:i'],
            'flexi_end'        => ['nullable','date_format:H:i'],
            'active'           => ['nullable','boolean'],
        ];
    }
}
