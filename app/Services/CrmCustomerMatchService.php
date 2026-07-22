<?php

namespace App\Services;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Support\PhoneNumberFormatter;
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

        // Normalize before comparing — client_phone is stored normalized
        // (Lead::setClientPhoneAttribute()), so an un-normalized raw
        // lookup value (e.g. "2072139077" vs. the stored "+1 (207)
        // 213-9077") would otherwise never match and spawn a duplicate.
        $phone = $phone ? PhoneNumberFormatter::format($phone) : $phone;

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

        // Normalize before comparing — see findLeadByContact() above for why.
        $phone = $phone ? PhoneNumberFormatter::format($phone) : $phone;

        return Customer::where(function ($q) use ($email, $phone) {
            // Case-insensitive on email — customers.email is unique, so a
            // case-only mismatch here (e.g. an import capitalizing what's
            // already on file) would otherwise miss the match and attempt
            // to insert a second row that collides with the DB constraint.
            if ($email) {
                $q->orWhereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        })->first();
    }

    /**
     * The specific "is this really the same person" check used when *creating*
     * a customer: a duplicate is only a name+email match together — same name
     * with a different email is a different person and is allowed through as
     * a brand-new customer, not silently merged or blocked. (Deliberately
     * narrower than findCustomerByContact(), which several other cross-
     * referencing lookups — e.g. shipment delay flag syncing — still rely on
     * matching by email-or-phone alone; this one is only for create flows.)
     */
    public function findDuplicateCustomer(?string $name, ?string $email): ?Customer
    {
        if (! $name || ! $email) {
            return null;
        }

        return Customer::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();
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

        // Normalize before comparing — see findLeadByContact() above for why.
        $phone = $phone ? PhoneNumberFormatter::format($phone) : $phone;

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

        // Normalize before comparing — see findLeadByContact() above for why.
        $phone = $phone ? PhoneNumberFormatter::format($phone) : $phone;

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
     * Lead's status to Delivered too, and set the "shipment_delivered" flag
     * on the matching eBay record and base Customer so the same delivery
     * shows up everywhere that customer appears — not just the Website CRM
     * lead. WebsiteLeadStatus::Delivered already existed as a terminal
     * status (excluded from the Active/Follow-Up-Due scopes) but nothing
     * ever actually set it — a lead stayed on whatever status it had before
     * the shipment finished (e.g. "In Delivery"), so the Customer Database
     * page kept showing a stale status even after the delivery was
     * complete. Skips a lead that's already terminal (Delivered/Lost) so
     * this can't resurrect a lead a staff member deliberately marked Lost.
     * Unlike shipment_delay, shipment_delivered is never cleared back to
     * false here — a past delivery stays a fact even if the customer later
     * gets a new, still-pending shipment; a fresh Problem still surfaces
     * via the separate shipment_delay flag regardless.
     */
    public function syncDeliveryStatus(ShipmentCustomer $shipmentCustomer): void
    {
        if ($shipmentCustomer->status !== ShipmentCustomer::STATUS_DELIVERED) {
            return;
        }

        $email = $shipmentCustomer->recipient_email;
        $phone = $shipmentCustomer->recipient_phone;
        $customerId = $shipmentCustomer->customer_id;

        $lead = ($customerId ? Lead::where('customer_id', $customerId)->first() : null)
            ?? $this->findLeadByContact($email, $phone);

        if ($lead && ! in_array($lead->status, [WebsiteLeadStatus::Delivered, WebsiteLeadStatus::Lost], true)) {
            $lead->update(['status' => WebsiteLeadStatus::Delivered]);
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => 'Shipment marked as Delivered.',
                'status_changed_to' => WebsiteLeadStatus::Delivered,
                'contacted_at'      => now(),
            ]);
        }

        $ebayRecord = ($customerId ? EbayCustomerRecord::where('customer_id', $customerId)->first() : null)
            ?? $this->findEbayRecordByContact($email, $phone);
        if ($ebayRecord && ! $ebayRecord->shipment_delivered) {
            $ebayRecord->update(['shipment_delivered' => true]);
        }

        $customer = $customerId ? $shipmentCustomer->customer : $this->findCustomerByContact($email, $phone);
        if ($customer && ! $customer->shipment_delivered) {
            $customer->update(['shipment_delivered' => true]);
        }
    }

    /**
     * Called once per row right after a Process Trucking import creates a
     * ShipmentCustomer. Every imported recipient ends up in the Customer
     * database, one way or another: if their phone or email matches an
     * existing Customer, this is the same person ordering again — link the
     * new shipment record to them (customer_id), refresh their stored
     * contact/address info from the import (which is often more current
     * than whatever's on file), and move them forward in the pipeline since
     * a new shipment just started for them. If there's no match, a brand
     * new Customer is created from the import data (source: Logistic) so
     * Process Trucking imports aren't a data dead-end for people who've
     * never come through the website or eBay — the whole point of matching
     * on phone/email in the first place is to avoid inserting a second row
     * for someone already on file, not to skip people who aren't.
     */
    public function syncImportedCustomer(ShipmentCustomer $shipmentCustomer): Customer
    {
        // customers.email is unique but nullable — an empty string is a
        // real, non-null value as far as that constraint is concerned, so
        // a second customer with no email would collide with the first
        // unless blanks are normalized to null here.
        $email = $shipmentCustomer->recipient_email ?: null;
        $phone = $shipmentCustomer->recipient_phone ?: null;

        $customer = $this->findCustomerByContact($email, $phone);

        // customers.email is unique regardless of soft-delete state — a
        // customer soft-deleted by maybeDeleteOrphanedCustomer() (e.g. their
        // only shipment record was removed) still occupies their email at
        // the DB level even though findCustomerByContact() can't see them.
        // Restore rather than attempting to create a second row for the
        // same email and hitting the unique constraint.
        if (! $customer && $email) {
            $trashed = Customer::onlyTrashed()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
            if ($trashed) {
                $trashed->restore();
                $customer = $trashed;
            }
        }

        if ($customer) {
            if ($shipmentCustomer->customer_id !== $customer->id) {
                $shipmentCustomer->update(['customer_id' => $customer->id]);
            }

            // Only overwrite fields the import actually supplied a value
            // for — a label that omitted, say, an email address must never
            // blank out an email already on file.
            $updates = array_filter([
                'name'    => $shipmentCustomer->recipient_name,
                'email'   => $email,
                'phone'   => $phone,
                'address' => $shipmentCustomer->shipping_address,
            ], fn ($v) => ! empty($v));
            if (! empty($updates)) {
                $customer->update($updates);
            }

            // A new shipment is now active for this customer — move them
            // forward to Active (never resurrect a deliberately Lost customer).
            if ($customer->status !== CustomerStatus::Lost && $customer->status !== CustomerStatus::Active) {
                $customer->update(['status' => CustomerStatus::Active]);
            }
        } else {
            try {
                $customer = Customer::create([
                    'name'       => $shipmentCustomer->recipient_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'address'    => $shipmentCustomer->shipping_address,
                    'status'     => CustomerStatus::Active,
                    'source'     => CustomerSource::Logistic,
                    'created_by' => auth()->id(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Email is unique on customers; a race (or a case/whitespace
                // variant findCustomerByContact() didn't catch) can still
                // collide here. Fall back to whoever actually holds that
                // email — including a trashed row, restoring it — rather
                // than failing the whole import row.
                $customer = $email ? Customer::withTrashed()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first() : null;
                if (! $customer) {
                    throw $e;
                }
                if ($customer->trashed()) {
                    $customer->restore();
                }
            }

            $shipmentCustomer->update(['customer_id' => $customer->id]);
        }

        $lead = Lead::where('customer_id', $customer->id)->first()
            ?? $this->findLeadByContact($email, $phone);

        // Same reasoning as the delivery/delay syncs above: never resurrect
        // a lead a staff member deliberately marked Lost, but a genuinely
        // new shipment IS reason enough to move a lead back to In Delivery
        // even if their last one already finished (Delivered) — this is a
        // new order, not a status correction on the old one.
        if ($lead && $lead->status !== WebsiteLeadStatus::Lost && $lead->status !== WebsiteLeadStatus::InDelivery) {
            $lead->update(['status' => WebsiteLeadStatus::InDelivery]);
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => 'New shipment imported via Process Trucking — auto-advanced to In Delivery.',
                'status_changed_to' => WebsiteLeadStatus::InDelivery,
                'contacted_at'      => now(),
            ]);
        }

        return $customer;
    }

    /**
     * Call after editing a ShipmentCustomer's own contact/address fields
     * (not just its status) so a linked Customer stays in sync going
     * forward, the same way syncImportedCustomer() keeps them in sync at
     * import time. Only touches a Customer that's actually linked via
     * customer_id — an edit to a shipment customer with no link doesn't
     * attempt a fresh contact match here, to avoid silently attaching a
     * shipment to the wrong person on a coincidental phone/email match
     * made during a manual edit rather than a fresh import.
     */
    public function syncEditedShipmentCustomer(ShipmentCustomer $shipmentCustomer): void
    {
        if (! $shipmentCustomer->customer_id) {
            return;
        }

        $customer = $shipmentCustomer->customer;
        if (! $customer) {
            return;
        }

        $updates = array_filter([
            'name'    => $shipmentCustomer->recipient_name,
            'email'   => $shipmentCustomer->recipient_email,
            'phone'   => $shipmentCustomer->recipient_phone,
            'address' => $shipmentCustomer->shipping_address,
        ], fn ($v) => ! empty($v));

        if (! empty($updates)) {
            $customer->update($updates);
        }
    }

    /**
     * Call after deleting a ShipmentCustomer that was linked to a Customer.
     * Deletes the Customer too, but only when this logistics workflow is
     * entirely responsible for that Customer existing in the first place —
     * source must be Logistic AND they must have no other activity left
     * (no other shipment record, no Lead, no eBay record, no logged
     * interaction). A customer with any other real history is never
     * touched here, even though the shipment link that brought them up
     * is gone — deleting one shipment record must not erase a person's
     * whole CRM history just because it happened to be reachable through
     * this particular row. Call this AFTER the ShipmentCustomer row is
     * already deleted, so its own row doesn't count against itself in the
     * "any other shipment record" check.
     */
    public function maybeDeleteOrphanedCustomer(?int $customerId): void
    {
        if (! $customerId) {
            return;
        }

        $customer = Customer::find($customerId);
        if (! $customer || $customer->source !== CustomerSource::Logistic->value) {
            return;
        }

        $hasOtherActivity = ShipmentCustomer::where('customer_id', $customerId)->exists()
            || Lead::where('customer_id', $customerId)->exists()
            || EbayCustomerRecord::where('customer_id', $customerId)->exists()
            || CustomerInteraction::where('customer_id', $customerId)->exists();

        if ($hasOtherActivity) {
            return;
        }

        $customer->delete();
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

        // A record is identified by its customer_id FK or its email — either
        // matching an earlier row means it's the same person, so a Lead/eBay/
        // Shipment row can carry a typo'd or stale email while still being
        // correctly linked via customer_id. Phone is deliberately NOT used as
        // a match signal here: unlike email, the same phone number routinely
        // gets reused across genuinely different people (a shared household
        // line, a staff member's own number used as a placeholder while
        // testing, etc.), and matching on it silently swallowed whole
        // customer profiles — a person's real eBay purchase history could
        // vanish from every directory view just because an unrelated Lead
        // elsewhere happened to share their phone number.
        $keysFor = function (?string $email, ?string $phone, string $fallback, ?int $customerId = null): array {
            $keys = [];
            if ($customerId) {
                $keys[] = 'customer-' . $customerId;
            }
            if ($email) {
                $keys[] = 'email-' . strtolower(trim($email));
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

        Lead::with('handler', 'techSupportCase', 'latestOrder')->get()->each(function (Lead $lead) use (&$out, $keysFor, $anySeen, $reserve) {
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
                // Two distinct dates: when this lead first came in (always
                // set) vs. their most recent actual purchase (only set if
                // they've bought something — a fresh inquiry with no order
                // yet has no purchase date at all, not today's date).
                'created_date'  => $lead->received_at ?? $lead->created_at,
                'purchase_date' => $lead->latestOrder?->order_date,
                'category'    => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalSupport => 'technical',
                    $lead->status === WebsiteLeadStatus::DelayedShipment  => 'shipment_delay',
                    default => null,
                },
            ]);
        });

        EbayCustomerRecord::with('handlerHistory.user', 'techSupportCase', 'orders')->get()->each(function (EbayCustomerRecord $record) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($record->email, $record->phone, 'ebay-' . $record->id, $record->customer_id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            // A negative-feedback report caused by "Logistic issues" belongs
            // on the real Logistic Issues page the same way an active
            // shipment problem already does — computed live from the tab +
            // cause rather than a stored flag, so it never goes stale
            // (unchecking the cause or moving off the negative-feedback
            // status just stops matching here, no separate cleanup needed).
            $hasLogisticCause = in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES], true)
                && in_array('Logistic issues', $record->negative_feedback_causes ?? [], true);
            $out->push([
                'source'      => 'eBay',
                'source_icon' => '🛒',
                'source_color'=> '#f59e0b',
                'id'          => $record->id,
                'name'        => $record->buyer_name ?: $record->username,
                'email'       => $record->email,
                'phone'       => $record->phone,
                'status_label'=> match (true) {
                    $record->shipment_delay || $hasLogisticCause => 'Logistic issues',
                    $record->shipment_delivered  => 'Delivered',
                    default                       => EbayCustomerRecord::tabs()[$record->tab_type] ?? $record->tab_type,
                },
                'status_color'=> match (true) {
                    $record->shipment_delay || $hasLogisticCause => EbayCustomerRecord::LOGISTIC_ISSUES_COLOR,
                    $record->shipment_delivered  => EbayCustomerRecord::DELIVERED_COLOR,
                    default                       => EbayCustomerRecord::tabColor($record->tab_type),
                },
                'occurrence_label' => $record->techSupportCase?->occurrence_label,
                'handler'     => $record->current_handler?->name,
                'link'        => route('crm.ebay.customers.show', $record),
                // orders() is already ordered newest-first, so the first
                // entry is the most recent purchase — null if none logged yet.
                'created_date'  => $record->created_at,
                'purchase_date' => $record->orders->first()?->ordered_at,
                'category'    => match (true) {
                    $record->tab_type === EbayCustomerRecord::TAB_TECHNICAL => 'technical',
                    $record->shipment_delay || $hasLogisticCause => 'shipment_delay',
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
                    // Prefer the actual customer's own profile over the shipment
                    // page — a customer can have several shipments (some fine,
                    // some not), so landing on one specific delivery is less
                    // useful than landing on the person. Falls back to the
                    // shipment page, or — for a Process Trucking import not yet
                    // assigned to any shipment — the Process Trucking tab itself,
                    // since shipment_id can be null there.
                    'link'        => match (true) {
                        (bool) $sc->customer_id => route('crm.customers.show', $sc->customer_id),
                        (bool) $sc->shipment_id  => route('crm.logistics.shipments.show', $sc->shipment_id),
                        default                  => route('crm.logistics.shipments.index', ['status' => 'processing']),
                    },
                    // No purchase concept at this level — a Logistics-flagged
                    // row is a shipment problem, not a sale.
                    'created_date'  => $sc->shipment?->created_at ?? $sc->created_at,
                    'purchase_date' => null,
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
            // A Customer row with no matching Lead/eBay record falls back to
            // this branch — shown as "eBay" or "Logistics" if the Customer
            // itself is tagged that way (e.g. auto-created by a Process
            // Trucking import), otherwise "Website", since those two plus a
            // plain website inquiry cover every acquisition channel this
            // business actually has today.
            $sourceLabel = match ($customer->source) {
                \App\Enums\CustomerSource::Ebay->value     => 'eBay',
                \App\Enums\CustomerSource::Logistic->value => 'Logistics',
                default                                     => 'Website',
            };
            $out->push([
                'source'      => $sourceLabel,
                'source_icon' => match ($sourceLabel) { 'eBay' => '🛒', 'Logistics' => '🚚', default => '🌐' },
                'source_color'=> match ($sourceLabel) { 'eBay' => '#f59e0b', 'Logistics' => '#0ea5e9', default => '#8b5cf6' },
                'id'          => $customer->id,
                'name'        => $customer->name,
                'email'       => $customer->email,
                'phone'       => $customer->phone,
                'status_label'=> match (true) {
                    $customer->shipment_delay     => 'Logistic issues',
                    $customer->shipment_delivered => 'Delivered',
                    default                        => $customer->status?->label() ?? $customer->status,
                },
                'status_color'=> match (true) {
                    $customer->shipment_delay     => EbayCustomerRecord::LOGISTIC_ISSUES_COLOR,
                    $customer->shipment_delivered => EbayCustomerRecord::DELIVERED_COLOR,
                    default                        => $customer->status?->color() ?? '#94a3b8',
                },
                'occurrence_label' => $customer->latestTechSupportCase?->occurrence_label,
                'handler'     => $customer->assignee?->name,
                'link'        => route('crm.customers.show', $customer),
                // A bare Customer row (no matching Lead/eBay record) has no
                // order history reachable from here — only Leads and eBay
                // records track purchases directly.
                'created_date'  => $customer->created_at,
                'purchase_date' => null,
                'category'    => $customer->shipment_delay ? 'shipment_delay' : null,
            ]);
        });

        if ($search = $filters['search'] ?? null) {
            $search = strtolower($search);
            $out = $out->filter(fn ($c) => str_contains(strtolower($c['name'] ?? ''), $search)
                || str_contains(strtolower($c['email'] ?? ''), $search)
                || str_contains(strtolower($c['phone'] ?? ''), $search));
        }

        // Newest customers first — ranked by whichever is more recent between
        // their purchase date and their created date (a repeat buyer's new
        // order re-surfaces them at the top even if they first came in long
        // ago; a brand new inquiry with no purchase yet still ranks by when
        // they showed up).
        return $out->sortByDesc(fn ($c) => max($c['purchase_date']?->timestamp ?? 0, $c['created_date']?->timestamp ?? 0))->values();
    }

    /** Deduplicated total customer count, for the Dashboard KPI tile. */
    public function dedupedCustomerCount(): int
    {
        return $this->buildUnifiedDirectory()->count();
    }
}
