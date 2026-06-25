<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskUserReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_id' => 'required|string',
            'answer' => 'required|string|max:1000',
        ];
    }

    public function getRequestId(): string
    {
        return $this->input('request_id');
    }

    public function getAnswer(): string
    {
        return $this->input('answer');
    }
}
