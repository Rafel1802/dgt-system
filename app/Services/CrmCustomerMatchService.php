<?php

namespace App\Services;

use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use Illuminate\Support\Collection;

/**
 * Cross-source customer matching, delay-propagation, and dedup for the CRM module.
 *
 * Mirrors the demo's matchByContact()/propagateShipmentProblem()/buildUnifiedCustomers()
 * behaviour, but writes through real Eloquent models instead of an in-memory store.
 */
class CrmCustomerMatchService
{
    public function findLeadByContact(?string $email, ?string $phone): ?Lead
    {
        if (! $email && ! $phone) {
            return null;
        }

        return Lead::where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('client_email', $email);
            }
            if ($phone) {
                $q->orWhere('client_phone', $phone);
            }
        })->first();
    }

    /** Find an existing Customer by matching email or phone, so auto-create flows never spawn duplicates. */
    public function findCustomerByContact(?string $email, ?string $phone): ?Customer
    {
        if (! $email && ! $phone) {
            return null;
        }

        return Customer::where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        })->first();
    }

    /**
     * Find an existing eBay customer record by username (the natural unique
     * identifier for an eBay account) or by matching email/phone, so a second
     * "New Record" for the same person doesn't fork their history across two
     * rows — handler history, status history, follow-ups, and orders are all
     * meant to live on a single record per customer.
     */
    public function findEbayRecordByUsernameOrContact(?string $username, ?string $email, ?string $phone): ?EbayCustomerRecord
    {
        if (! $username && ! $email && ! $phone) {
            return null;
        }

        return EbayCustomerRecord::where(function ($q) use ($username, $email, $phone) {
            if ($username) {
                $q->orWhere('username', $username);
            }
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        })->first();
    }

    public function findEbayRecordByContact(?string $email, ?string $phone): ?EbayCustomerRecord
    {
        if (! $email && ! $phone) {
            return null;
        }

        return EbayCustomerRecord::where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        })->first();
    }

    /**
     * When a shipment customer is marked "Problem", flip the matching CRM lead
     * to Logistic Issues, the matching eBay record's shipment_delay flag, and
     * the base Customer record's own shipment_delay flag (so the flag shows up
     * everywhere that customer appears — their own profile page included, not
     * just the unified directory), recording the transition where each source
     * keeps its own history trail.
     */
    public function propagateShipmentProblem(ShipmentCustomer $shipmentCustomer): void
    {
        $email = $shipmentCustomer->recipient_email;
        $phone = $shipmentCustomer->recipient_phone;
        $customerId = $shipmentCustomer->customer_id;

        // Prefer the direct customer_id link (set when the recipient was
        // picked via the customer combobox) over contact matching wherever
        // possible — recipient_email/phone on the shipment customer can be
        // manually edited to something unrelated to the linked Customer, in
        // which case contact matching alone would silently miss the real match.
        $lead = ($customerId ? Lead::where('customer_id', $customerId)->first() : null)
            ?? $this->findLeadByContact($email, $phone);
        if ($lead && $lead->status !== WebsiteLeadStatus::DelayedShipment) {
            $lead->update(['status' => WebsiteLeadStatus::DelayedShipment]);
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => 'Shipment marked as Problem — auto-flagged as Logistic Issues.',
                'status_changed_to' => WebsiteLeadStatus::DelayedShipment,
                'contacted_at'      => now(),
            ]);
        }

        $ebayRecord = ($customerId ? EbayCustomerRecord::where('customer_id', $customerId)->first() : null)
            ?? $this->findEbayRecordByContact($email, $phone);
        if ($ebayRecord && ! $ebayRecord->shipment_delay) {
            $ebayRecord->update(['shipment_delay' => true]);
        }

        $customer = $customerId
            ? $shipmentCustomer->customer
            : $this->findCustomerByContact($email, $phone);

        if ($customer && ! $customer->shipment_delay) {
            $customer->update(['shipment_delay' => true]);
        }
    }

    /**
     * Deduplicated cross-source customer directory (Leads + eBay records +
     * Logistics "Problem" shipment customers + any remaining Customer Database
     * rows not already surfaced by one of those), matched by lowercased email-or-phone.
     *
     * Lead/eBay/Shipment are matched first so their technical/shipment-delay/
     * negative-feedback category detection always wins; plain Customer records
     * only fill in the gap so every real customer still appears somewhere.
     */
    public function buildUnifiedDirectory(array $filters = []): Collection
    {
        $seen = [];
        $out = collect();

        // A record can be identified by up to three independent signals: its
        // customer_id FK, its email, and its phone. Any one of these matching
        // an earlier row means it's the same person — a Lead/eBay/Shipment row
        // can carry a typo'd or stale email while still being correctly linked
        // via customer_id, and that link needs to cross-match against a plain
        // Customer row (or another source) found only by contact info, in
        // either direction. So every pass reserves ALL of its known signals,
        // and checks against ALL of them, rather than collapsing to one key.
        $keysFor = function (?string $email, ?string $phone, string $fallback, ?int $customerId = null): array {
            $keys = [];
            if ($customerId) {
                $keys[] = 'customer-' . $customerId;
            }
            if ($email) {
                $keys[] = 'email-' . strtolower(trim($email));
            }
            if ($phone) {
                $keys[] = 'phone-' . strtolower(trim($phone));
            }
            if (empty($keys)) {
                $keys[] = 'fallback-' . strtolower(trim($fallback));
            }
            return $keys;
        };
        $anySeen = function (array $keys) use (&$seen): bool {
            foreach ($keys as $k) {
                if (isset($seen[$k])) {
                    return true;
                }
            }
            return false;
        };
        $reserve = function (array $keys) use (&$seen): void {
            foreach ($keys as $k) {
                $seen[$k] = true;
            }
        };

        Lead::with('handler')->get()->each(function (Lead $lead) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($lead->client_email, $lead->client_phone, 'lead-' . $lead->id, $lead->customer_id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            $out->push([
                'source'      => $lead->source?->label() ?? 'Website',
                'source_icon' => $lead->source?->icon() ?? '🌐',
                'source_color'=> $lead->source?->color() ?? '#8b5cf6',
                'id'          => $lead->id,
                'name'        => $lead->client_name,
                'email'       => $lead->client_email,
                'phone'       => $lead->client_phone,
                'status_label'=> $lead->status->label(),
                'status_color'=> $lead->status->color(),
                'handler'     => $lead->handler?->name,
                'link'        => route('crm.website.show', $lead),
                'category'    => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalSupport   => 'technical',
                    $lead->status === WebsiteLeadStatus::DelayedShipment    => 'shipment_delay',
                    default => null,
                },
            ]);
        });

        EbayCustomerRecord::with('handlerHistory.user')->get()->each(function (EbayCustomerRecord $record) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($record->email, $record->phone, 'ebay-' . $record->id, $record->customer_id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            $out->push([
                'source'      => 'eBay',
                'source_icon' => '🛒',
                'source_color'=> '#f59e0b',
                'id'          => $record->id,
                'name'        => $record->buyer_name ?: $record->username,
                'email'       => $record->email,
                'phone'       => $record->phone,
                'status_label'=> $record->shipment_delay ? 'Logistic issues' : (EbayCustomerRecord::tabs()[$record->tab_type] ?? $record->tab_type),
                'status_color'=> $record->shipment_delay ? EbayCustomerRecord::LOGISTIC_ISSUES_COLOR : EbayCustomerRecord::tabColor($record->tab_type),
                'handler'     => $record->current_handler?->name,
                'link'        => route('crm.ebay.customers.edit', $record),
                'category'    => match (true) {
                    $record->tab_type === EbayCustomerRecord::TAB_TECHNICAL => 'technical',
                    $record->shipment_delay => 'shipment_delay',
                    in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES]) => 'negative_feedback',
                    default => null,
                },
            ]);
        });

        ShipmentCustomer::with('shipment')
            ->where('status', ShipmentCustomer::STATUS_PROBLEM)
            ->get()
            ->each(function (ShipmentCustomer $sc) use (&$out, $keysFor, $anySeen, $reserve) {
                $k = $keysFor($sc->recipient_email, $sc->recipient_phone, 'shipment-' . $sc->id, $sc->customer_id);
                if ($anySeen($k)) {
                    return;
                }
                $reserve($k);
                $out->push([
                    'source'      => 'Logistics',
                    'source_icon' => '🚚',
                    'source_color'=> '#0ea5e9',
                    'id'          => $sc->shipment_id,
                    'name'        => $sc->recipient_name,
                    'email'       => $sc->recipient_email,
                    'phone'       => $sc->recipient_phone,
                    'status_label'=> 'Logistic issues',
                    'status_color'=> EbayCustomerRecord::LOGISTIC_ISSUES_COLOR,
                    'handler'     => null,
                    'link'        => route('crm.logistics.shipments.show', $sc->shipment_id),
                    'category'    => 'shipment_delay',
                ]);
            });

        Customer::with('assignee')->get()->each(function (Customer $customer) use (&$out, $keysFor, $anySeen, $reserve) {
            // Reserving/checking its own id alongside email+phone means this
            // correctly cross-matches an earlier row whether that row was
            // linked via customer_id or only matched by contact info.
            $k = $keysFor($customer->email, $customer->phone, 'customer-' . $customer->id, $customer->id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            // Every customer originates either from eBay or from a CRM Website
            // inquiry channel — there is no separate Referral/Cold Call/Manual
            // etc. source in this business, so a Customer row with no matching
            // Lead/eBay record is just shown as "Website" (unless it was itself
            // tagged eBay-sourced), rather than surfacing the raw CustomerSource value.
            $isEbaySourced = $customer->source === \App\Enums\CustomerSource::Ebay->value;
            $out->push([
                'source'      => $isEbaySourced ? 'eBay' : 'Website',
                'source_icon' => $isEbaySourced ? '🛒' : '🌐',
                'source_color'=> $isEbaySourced ? '#f59e0b' : '#8b5cf6',
                'id'          => $customer->id,
                'name'        => $customer->name,
                'email'       => $customer->email,
                'phone'       => $customer->phone,
                'status_label'=> $customer->shipment_delay ? 'Logistic issues' : ($customer->status?->label() ?? $customer->status),
                'status_color'=> $customer->shipment_delay ? EbayCustomerRecord::LOGISTIC_ISSUES_COLOR : ($customer->status?->color() ?? '#94a3b8'),
                'handler'     => $customer->assignee?->name,
                'link'        => route('crm.customers.show', $customer),
                'category'    => $customer->shipment_delay ? 'shipment_delay' : null,
            ]);
        });

        if ($search = $filters['search'] ?? null) {
            $search = strtolower($search);
            $out = $out->filter(fn ($c) => str_contains(strtolower($c['name'] ?? ''), $search)
                || str_contains(strtolower($c['email'] ?? ''), $search)
                || str_contains(strtolower($c['phone'] ?? ''), $search));
        }

        return $out->values();
    }

    /** Deduplicated total customer count, for the Dashboard KPI tile. */
    public function dedupedCustomerCount(): int
    {
        return $this->buildUnifiedDirectory()->count();
    }
}
