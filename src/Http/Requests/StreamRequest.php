<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StreamRequest extends FormRequest
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
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|integer|exists:sql_agent_conversations,id',
        ];
    }

    public function getMessage(): string
    {
        return $this->input('message');
    }

    public function getConversationId(): ?int
    {
        $id = $this->input('conversation_id');

        return $id !== null ? (int) $id : null;
    }
}
