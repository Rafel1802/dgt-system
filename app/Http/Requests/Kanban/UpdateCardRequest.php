<?php

namespace App\Http\Requests\Kanban;

use App\Enums\CardLabel;
use App\Enums\CardPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $card = $this->route('card');
        return $this->user()->can('update', $card);
    }

    public function rules(): array
    {
        $labelValues    = array_column(CardLabel::cases(), 'value');
        $priorityValues = array_column(CardPriority::cases(), 'value');

        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'label'       => ['required', 'string', Rule::in($labelValues)],
            'sub_label'   => ['nullable', 'string', 'max:100'],
            'priority'    => ['required', 'string', Rule::in($priorityValues)],
            'deadline'    => ['nullable', 'date'],
            'assignees'   => ['nullable', 'array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ];
    }
}
