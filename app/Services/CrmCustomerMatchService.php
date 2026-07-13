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
 * Mirrors the demo's matchByContact()/syncShipmentDelayFlags()/buildUnifiedCustomers()
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
     * Keep the matching CRM lead's status, the matching eBay record's
     * shipment_delay flag, and the base Customer record's own shipment_delay
     * flag in sync with whether this customer currently has ANY shipment
     * customer record in "Problem" status — a customer can appear on
     * multiple shipments (or the same shipment more than once), so a single
     * row being resolved back to Delivered must not clear the flag while
     * another one of their shipments is still a Problem. Call this on every
     * shipment-customer save, not just transitions into Problem, so a save
     * that resolves the last remaining Problem correctly clears the flag
     * everywhere that customer appears (their own profile page and eBay
     * record included, not just the unified directory).
     */
    public function syncShipmentDelayFlags(ShipmentCustomer $shipmentCustomer): void
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

        $ebayRecord = ($customerId ? EbayCustomerRecord::where('customer_id', $customerId)->first() : null)
            ?? $this->findEbayRecordByContact($email, $phone);

        $customer = $customerId
            ? $shipmentCustomer->customer
            : $this->findCustomerByContact($email, $phone);

        $hasActiveProblem = $this->customerHasActiveProblemShipment($customerId, $email, $phone);

        if ($lead) {
            if ($hasActiveProblem && $lead->status !== WebsiteLeadStatus::DelayedShipment) {
                $lead->update(['status' => WebsiteLeadStatus::DelayedShipment]);
                LeadFollowUp::create([
                    'lead_id'           => $lead->id,
                    'user_id'           => auth()->id(),
                    'notes'             => 'Shipment marked as Problem — auto-flagged as Logistic Issues.',
                    'status_changed_to' => WebsiteLeadStatus::DelayedShipment,
                    'contacted_at'      => now(),
                ]);
            } elseif (! $hasActiveProblem && $lead->status === WebsiteLeadStatus::DelayedShipment) {
                $lead->update(['status' => WebsiteLeadStatus::InDelivery]);
                LeadFollowUp::create([
                    'lead_id'           => $lead->id,
                    'user_id'           => auth()->id(),
                    'notes'             => 'All linked shipments resolved — auto-cleared Logistic Issues.',
                    'status_changed_to' => WebsiteLeadStatus::InDelivery,
                    'contacted_at'      => now(),
                ]);
            }
        }

        if ($ebayRecord && $ebayRecord->shipment_delay !== $hasActiveProblem) {
            $ebayRecord->update(['shipment_delay' => $hasActiveProblem]);
        }

        if ($customer && $customer->shipment_delay !== $hasActiveProblem) {
            $customer->update(['shipment_delay' => $hasActiveProblem]);
        }
    }

    /**
     * When a shipment-customer is marked Delivered, flip the matched
     * Lead's status to Delivered too. WebsiteLeadStatus::Delivered already
     * existed as a terminal status (excluded from the Active/Follow-Up-Due
     * scopes) but nothing ever actually set it — a lead stayed on whatever
     * status it had before the shipment finished (e.g. "In Delivery"), so
     * the Customer Database page kept showing a stale status even after
     * the delivery was complete. Skips a lead that's already terminal
     * (Delivered/Lost) so this can't resurrect a lead a staff member
     * deliberately marked Lost.
     */
    public function syncDeliveryStatus(ShipmentCustomer $shipmentCustomer): void
    {
        if ($shipmentCustomer->status !== ShipmentCustomer::STATUS_DELIVERED) {
            return;
        }

        $customerId = $shipmentCustomer->customer_id;
        $lead = ($customerId ? Lead::where('customer_id', $customerId)->first() : null)
            ?? $this->findLeadByContact($shipmentCustomer->recipient_email, $shipmentCustomer->recipient_phone);

        if (! $lead || in_array($lead->status, [WebsiteLeadStatus::Delivered, WebsiteLeadStatus::Lost], true)) {
            return;
        }

        $lead->update(['status' => WebsiteLeadStatus::Delivered]);
        LeadFollowUp::create([
            'lead_id'           => $lead->id,
            'user_id'           => auth()->id(),
            'notes'             => 'Shipment marked as Delivered.',
            'status_changed_to' => WebsiteLeadStatus::Delivered,
            'contacted_at'      => now(),
        ]);
    }

    /**
     * Whether this customer (resolved via customer_id and/or contact info)
     * has any shipment-customer record — on any shipment — still in Problem
     * status. Matches on all known signals (not just customer_id) since some
     * shipment rows for the same real customer may predate the customer_id
     * link and only be identifiable by contact info.
     */
    private function customerHasActiveProblemShipment(?int $customerId, ?string $email, ?string $phone): bool
    {
        if (! $customerId && ! $email && ! $phone) {
            return false;
        }

        return ShipmentCustomer::where('status', ShipmentCustomer::STATUS_PROBLEM)
            ->where(function ($q) use ($customerId, $email, $phone) {
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
                if ($email) {
                    $q->orWhere('recipient_email', $email);
                }
                if ($phone) {
                    $q->orWhere('recipient_phone', $phone);
                }
            })
            ->exists();
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

        // eBay is processed before Leads: when the same person has both (e.g. a
        // logged phone/facebook/etc. inquiry *and* an eBay purchase record), their
        // eBay record should win the identity match and represent them everywhere
        // (All Customers, the Website CRM "customers with no lead" section) — not
        // get silently hidden behind a Lead and miscategorized as "Website". Their
        // Lead itself is untouched and still shows on its own Website CRM leads
        // table either way, since that page queries Lead directly, not this method.
        EbayCustomerRecord::with('handlerHistory.user', 'techSupportCase')->get()->each(function (EbayCustomerRecord $record) use (&$out, $keysFor, $anySeen, $reserve) {
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
                'occurrence_label' => $record->techSupportCase?->occurrence_label,
                'handler'     => $record->current_handler?->name,
                'link'        => route('crm.ebay.customers.show', $record),
                'category'    => match (true) {
                    $record->tab_type === EbayCustomerRecord::TAB_TECHNICAL => 'technical',
                    $record->shipment_delay => 'shipment_delay',
                    in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES]) => 'negative_feedback',
                    default => null,
                },
            ]);
        });

        Lead::with('handler', 'techSupportCase')->get()->each(function (Lead $lead) use (&$out, $keysFor, $anySeen, $reserve) {
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
                'status_label'=> $lead->status?->label() ?? '',
                'status_color'=> $lead->status?->color() ?? '#94a3b8',
                'occurrence_label' => $lead->techSupportCase?->occurrence_label,
                'handler'     => $lead->handler?->name,
                'link'        => route('crm.website.show', $lead),
                'category'    => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalSupport => 'technical',
                    $lead->status === WebsiteLeadStatus::DelayedShipment  => 'shipment_delay',
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
                    // Prefer the actual customer's own profile over the shipment
                    // page — a customer can have several shipments (some fine,
                    // some not), so landing on one specific delivery is less
                    // useful than landing on the person. Falls back to the
                    // shipment only when there's no linked Customer to send them to.
                    'link'        => $sc->customer_id
                        ? route('crm.customers.show', $sc->customer_id)
                        : route('crm.logistics.shipments.show', $sc->shipment_id),
                    'category'    => 'shipment_delay',
                ]);
            });

        Customer::with('assignee', 'latestTechSupportCase')->get()->each(function (Customer $customer) use (&$out, $keysFor, $anySeen, $reserve) {
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
                'occurrence_label' => $customer->latestTechSupportCase?->occurrence_label,
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
