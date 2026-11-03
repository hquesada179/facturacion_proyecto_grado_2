<?php

namespace App\Http\Requests\Integrations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization for this route is the HMAC signature
 * (VerifyWhatsappBridgeSignature), which runs before this request is
 * even built — authorize() here only needs to let validated() run.
 */
class WhatsappBridgeMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'wa_id' => ['required', 'string', 'max:32'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:4000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'message_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
