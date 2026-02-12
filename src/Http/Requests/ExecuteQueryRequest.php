<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteQueryRequest extends FormRequest
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
            'message_id' => 'required|integer|exists:sql_agent_messages,id',
            'query_index' => 'required|integer|min:0',
        ];
    }

    public function getMessageId(): int
    {
        return (int) $this->input('message_id');
    }

    public function getQueryIndex(): int
    {
        return (int) $this->input('query_index');
    }
}
