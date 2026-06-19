<?php

namespace App\Http\Requests\Kanban;

use App\Enums\CardLabel;
use App\Enums\CardPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kanban.create');
    }

    public function rules(): array
    {
        $labelValues   = array_column(CardLabel::cases(), 'value');
        $priorityValues = array_column(CardPriority::cases(), 'value');

        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'label'       => ['required', 'string', Rule::in($labelValues)],
            'sub_label'   => ['nullable', 'string', 'max:100'],
            'priority'    => ['required', 'string', Rule::in($priorityValues)],
            'deadline'    => ['nullable', 'date', 'after_or_equal:today'],
            'assignees'   => ['nullable', 'array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required'   => 'Please select a label for this task.',
            'label.in'         => 'Please select a valid label.',
            'priority.in'      => 'Please select a valid priority.',
            'deadline.after_or_equal' => 'Deadline must be today or a future date.',
        ];
    }
}
