<?php

namespace App\Http\Requests\Crm;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name'              => ['required', 'string', 'max:255', 'regex:' . StoreCustomerRequest::NAME_REGEX],
            'email'             => ['nullable', 'email', 'max:255', "unique:customers,email,{$customer->id}"],
            'phone'             => ['nullable', 'string', 'max:30', 'regex:' . StoreCustomerRequest::US_PHONE_REGEX],
            'company'           => ['nullable', 'string', 'max:255'],
            'job_title'         => ['nullable', 'string', 'max:100'],
            'website'           => ['nullable', 'url', 'max:255'],
            'country'           => ['nullable', 'string', 'max:10'],
            'state'             => ['nullable', 'string', 'max:100'],
            'city'              => ['nullable', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:500'],
            'status'            => ['required', Rule::enum(CustomerStatus::class)],
            'source'            => ['nullable', Rule::enum(CustomerSource::class)],
            'pipeline_stage'    => ['nullable', Rule::enum(DealStage::class)],
            'product_interests' => ['nullable', 'array'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'assigned_to'       => ['nullable', 'integer', 'exists:users,id'],
            'tags'              => ['nullable', 'string'],
            // Existing customers with no Purchase Date on file stay valid
            // without one, but once set (or on any record going forward) it
            // must remain present — required only when already populated.
            'first_purchase_date' => [
                $customer && $customer->first_purchase_date ? 'required' : 'nullable',
                'date', 'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'  => 'Name can only contain letters and spaces.',
            'phone.regex' => 'Enter a valid US phone number, e.g. (207) 213-9077.',
            'first_purchase_date.required' => 'Purchase Date is required.',
            'first_purchase_date.before_or_equal' => 'Purchase Date cannot be in the future.',
        ];
    }
}
