<?php

namespace App\Http\Requests\Crm;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    // Accepts common US/Canada (NANP) formats: (207) 213-9077, 207-213-9077,
    // 207.213.9077, 2072139077, +1 207-213-9077 — matches what
    // PhoneNumberFormatter normalizes on save.
    public const US_PHONE_REGEX = '/^\+?1?[-.\s]?\(?[2-9][0-9]{2}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}$/';

    // Letters (any language) and spaces only — no digits or symbols.
    public const NAME_REGEX = '/^[\p{L}\s]+$/u';

    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            // No blanket email uniqueness here — a duplicate is specifically a
            // name+email match together (see findDuplicateCustomer()); the
            // same email under a different name is a different person.
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30', 'regex:' . self::US_PHONE_REGEX],
            'company'           => ['nullable', 'string', 'max:255'],
            'job_title'         => ['nullable', 'string', 'max:100'],
            'website'           => ['nullable', 'url', 'max:255'],
            'country'           => ['nullable', 'string', 'max:10'],
            'state'             => ['nullable', 'string', 'max:100'],
            'city'              => ['nullable', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:500'],
            'postcode'          => ['nullable', 'string', 'max:20'],
            'status'            => ['required', Rule::enum(CustomerStatus::class)],
            'source'            => ['nullable', Rule::enum(CustomerSource::class)],
            'pipeline_stage'    => ['nullable', Rule::enum(DealStage::class)],
            'product_interests' => ['nullable', 'array'],
            'product_interests.*' => ['string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'assigned_to'       => ['nullable', 'integer', 'exists:users,id'],
            'tags'              => ['nullable', 'string'],
            // Every newly created customer must have a Purchase Date.
            'first_purchase_date' => ['required', 'date', 'before_or_equal:today'],
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
