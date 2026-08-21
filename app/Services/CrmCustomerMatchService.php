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
use Illuminate\Support\Facades\Cache;

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
     * Phone-only match used for the *soft* duplicate warning on the create
     * form — deliberately not folded into findDuplicateCustomer() (which
     * hard-blocks), since a shared phone number (household, shared work
     * line) routinely belongs to genuinely different people, unlike email.
     * Staff get shown the match and choose to proceed or not; nothing here
     * blocks on its own.
     */
    public function findCustomerByPhoneOnly(?string $phone): ?Customer
    {
        if (! $phone) {
            return null;
        }

        $phone = PhoneNumberFormatter::format($phone);

        return Customer::where('phone', $phone)->first();
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
            if ($hasActiveProblem && $lead->status !== WebsiteLeadStatus::PendingDelivery) {
                $lead->update(['status' => WebsiteLeadStatus::PendingDelivery]);
                LeadFollowUp::create([
                    'lead_id'           => $lead->id,
                    'user_id'           => auth()->id(),
                    'notes'             => 'Shipment marked as Problem — auto-flagged as Logistic Issues.',
                    'status_changed_to' => WebsiteLeadStatus::PendingDelivery,
                    'contacted_at'      => now(),
                ]);
            } elseif (! $hasActiveProblem && $lead->status === WebsiteLeadStatus::PendingDelivery) {
                $lead->update(['status' => WebsiteLeadStatus::PendingDelivery]);
                LeadFollowUp::create([
                    'lead_id'           => $lead->id,
                    'user_id'           => auth()->id(),
                    'notes'             => 'All linked shipments resolved — auto-cleared Logistic Issues.',
                    'status_changed_to' => WebsiteLeadStatus::PendingDelivery,
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

        self::forgetUnifiedDirectoryCache();
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

        if ($lead && ! in_array($lead->status, [WebsiteLeadStatus::Delivered, WebsiteLeadStatus::LostInterest], true)) {
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

        self::forgetUnifiedDirectoryCache();
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
        if ($lead && $lead->status !== WebsiteLeadStatus::LostInterest && $lead->status !== WebsiteLeadStatus::PendingDelivery) {
            $lead->update(['status' => WebsiteLeadStatus::PendingDelivery]);
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => 'New shipment imported via Process Trucking — auto-advanced to In Delivery.',
                'status_changed_to' => WebsiteLeadStatus::PendingDelivery,
                'contacted_at'      => now(),
            ]);
        }

        self::forgetUnifiedDirectoryCache();
        Cache::forget('crm.shipment_picker_customers');
        Cache::forget('crm.shipment_picker_customers.v2');

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
     * Cache key for the base (unfiltered) unified directory.
     * v3: includes created_ts/purchase_ts + lowercase search fields so list
     * pages can filter/sort without re-parsing Carbon on every row.
     * Invalidated via forgetUnifiedDirectoryCache() on CRM writes.
     */
    public const UNIFIED_DIRECTORY_CACHE_KEY = 'crm.unified_directory.base.v3';

    /** Drop cached directory + count (call after any create/update that changes who appears). */
    public static function forgetUnifiedDirectoryCache(): void
    {
        Cache::forget(self::UNIFIED_DIRECTORY_CACHE_KEY);
        // Legacy key from v2 — safe forget if any process still warms it.
        Cache::forget('crm.unified_directory.base.v2');
        Cache::forget('crm.deduped_customer_count');
        Cache::forget('crm.dashboard_stats');
        // Day-scoped dashboard KPI block
        Cache::forget('crm.dashboard.page_kpis.' . now()->toDateString());
        if (app()->bound('crm.unified_directory.base_collection')) {
            app()->forgetInstance('crm.unified_directory.base_collection');
        }
    }

    /** Safely rebuild the cache in the background without forcing a cold hit */
    public static function rebuildUnifiedDirectoryCache(): void
    {
        self::forgetUnifiedDirectoryCache();

        // Rebuild immediately in the background
        $service = app(self::class);
        $rows = $service->buildBaseUnifiedDirectoryRows();
        Cache::put(self::UNIFIED_DIRECTORY_CACHE_KEY, $rows, 86400);
        
        $collection = collect($rows);
        app()->instance('crm.unified_directory.base_collection', $collection);
    }

    /**
     * Deduplicated cross-source customer directory (Leads + eBay records +
     * Logistics "Problem" shipment customers + any remaining Customer Database
     * rows not already surfaced by one of those), matched by lowercased email-or-phone.
     *
     * Lead/eBay/Shipment are matched first so their technical/shipment-delay/
     * negative-feedback category detection always wins; plain Customer records
     * only fill in the gap so every real customer still appears somewhere.
     *
     * Performance: base directory is cached ~180s (same row shape + dedupe rules);
     * search/sort still applied in-process so filter semantics stay identical.
     * Returns Carbon dates on created_date/purchase_date for Blade/callers.
     */
    public function buildUnifiedDirectory(array $filters = []): Collection
    {
        return $this->hydrateDirectoryDates(
            $this->filterAndSortDirectory($this->baseUnifiedDirectory(), $filters)
        );
    }

    /**
     * Same rows as buildUnifiedDirectory(), but dates stay as ISO strings +
     * integer timestamps. Use on list pages that only need to hydrate the
     * current paginator page (Customers DB is the main consumer).
     */
    public function buildUnifiedDirectoryRaw(array $filters = []): Collection
    {
        return $this->filterAndSortDirectory($this->baseUnifiedDirectory(), $filters);
    }

    /**
     * Convert ISO date strings (or timestamps) to Carbon for a small page slice.
     */
    public function hydrateDirectoryDates(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            foreach (['created_date' => 'created_ts', 'purchase_date' => 'purchase_ts'] as $dateKey => $tsKey) {
                if ($row[$dateKey] instanceof \DateTimeInterface) {
                    continue;
                }
                if (is_string($row[$dateKey] ?? null) && $row[$dateKey] !== '') {
                    try {
                        $row[$dateKey] = \Illuminate\Support\Carbon::parse($row[$dateKey]);
                        continue;
                    } catch (\Throwable) {
                        $row[$dateKey] = null;
                    }
                } elseif (! empty($row[$tsKey])) {
                    $row[$dateKey] = \Illuminate\Support\Carbon::createFromTimestamp((int) $row[$tsKey]);
                } else {
                    $row[$dateKey] = null;
                }
            }

            return $row;
        })->values();
    }

    /**
     * Search + sort on precomputed scalar fields (no Carbon).
     */
    private function filterAndSortDirectory(Collection $out, array $filters): Collection
    {
        if ($search = $filters['search'] ?? null) {
            $search = strtolower(trim((string) $search));
            if ($search !== '') {
                $out = $out->filter(function (array $c) use ($search) {
                    return str_contains($c['name_l'] ?? strtolower((string) ($c['name'] ?? '')), $search)
                        || str_contains($c['email_l'] ?? strtolower((string) ($c['email'] ?? '')), $search)
                        || str_contains($c['phone_l'] ?? strtolower((string) ($c['phone'] ?? '')), $search);
                });
            }
        }

        // Default sorting:
        // Website & eBay customers (non-Logistics sources) rank ON TOP sorted by created_ts descending.
        // Logistics-only customers rank below Website & eBay customers.
        $sortBy = $filters['sort_by'] ?? null;

        if ($sortBy === 'skip') {
            return $out->values();
        }

        if ($sortBy === 'purchase') {
            return $out->sortByDesc(fn (array $c) => $c['purchase_ts'] ?? -1)->values();
        }

        return $out->sort(function (array $a, array $b) {
            $aIsLogistics = ($a['source'] ?? '') === 'Logistics';
            $bIsLogistics = ($b['source'] ?? '') === 'Logistics';

            if ($aIsLogistics !== $bIsLogistics) {
                return $aIsLogistics ? 1 : -1;
            }

            $aTs = (int) ($a['created_ts'] ?? 0);
            $bTs = (int) ($b['created_ts'] ?? 0);

            if ($aTs !== $bTs) {
                return $bTs <=> $aTs;
            }

            $aPur = (int) ($a['purchase_ts'] ?? 0);
            $bPur = (int) ($b['purchase_ts'] ?? 0);
            return $bPur <=> $aPur;
        })->values();
    }

    /**
     * Base directory without search/sort — shared across Customer DB, Website
     * side-rows, logistic issues, and dashboard count. Cached for 180 seconds
     * in production; never cached under PHPUnit (tests need fresh rows).
     *
     * Rows keep ISO date strings + integer timestamps (no Carbon) so cache hits
     * stay cheap until a caller hydrates a page slice.
     */
    private function baseUnifiedDirectory(): Collection
    {
        // Per-request memo (container-scoped) so one page pays once.
        if (app()->bound('crm.unified_directory.base_collection')) {
            return app('crm.unified_directory.base_collection');
        }

        $rows = app()->runningUnitTests()
            ? $this->buildBaseUnifiedDirectoryRows()
            : Cache::remember(self::UNIFIED_DIRECTORY_CACHE_KEY, 86400, fn () => $this->buildBaseUnifiedDirectoryRows());

        $collection = collect($rows);

        app()->instance('crm.unified_directory.base_collection', $collection);

        return $collection;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildBaseUnifiedDirectoryRows(): array
    {
        $seen = [];
        $out = collect();

        // NOTE: Search is applied only after cross-source dedupe (in buildUnifiedDirectory).
        // Pre-filtering each source in SQL would change which row "wins"
        // the dedupe and alter search results — business logic must stay identical.

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

        Lead::query()
            ->select([
                'id', 'customer_id', 'handled_by', 'client_name', 'client_email', 'client_phone',
                'source', 'status', 'received_at', 'created_at',
            ])
            ->with([
                'customer:id,email,phone,lifetime_value',
                'handler:id,name',
                'techSupportCase',
                'latestOrder',
                'orders.items',
            ])
            ->get()
            ->each(function (Lead $lead) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($lead->client_email, $lead->client_phone, 'lead-' . $lead->id, $lead->customer_id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            $leadVal = (float) $lead->orders->flatMap->items->sum(fn ($i) => (float) $i->price * (int) $i->quantity);
            if ($leadVal == 0) {
                $leadVal = (float) ($lead->customer?->lifetime_value ?? 0);
            }

            $out->push([
                'source'      => 'Website',
                'source_icon' => '🌐',
                'source_color'=> '#3b82f6',
                'id'          => $lead->id,
                'name'        => $lead->client_name,
                'email'       => $lead->client_email,
                'phone'       => $lead->client_phone,
                'lifetime_value' => $leadVal,
                'status_label'=> $lead->status?->label() ?? '',
                'status_color'=> $lead->status?->color() ?? '#94a3b8',
                'occurrence_label' => $lead->techSupportCase?->occurrence_label,
                'handler'     => $lead->handler?->name,
                'handler_id'  => $lead->handled_by,
                'link'        => route('crm.website.show', $lead),
                'created_date'  => $lead->received_at ?? $lead->created_at,
                'purchase_date' => $lead->latestOrder?->order_date,
                'status_label'  => $lead->status?->label() ?? '',
                'status_color'  => $lead->status?->color() ?? '#94a3b8',
                'status_badges' => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalIssues => [
                        ['label' => 'Technical Support', 'color' => '#8b5cf6', 'category' => 'technical']
                    ],
                    $lead->status === WebsiteLeadStatus::PendingDelivery => [
                        ['label' => 'Delayed Shipment', 'color' => '#10b981', 'category' => 'shipment_delay']
                    ],
                    $lead->status === WebsiteLeadStatus::SuccessfulLead || $lead->status === WebsiteLeadStatus::LostInterest => [
                        ['label' => $lead->status?->label() ?? 'Resolved', 'color' => '#0ea5e9', 'category' => 'resolved']
                    ],
                    default => [
                        ['label' => $lead->status?->label() ?? '', 'color' => $lead->status?->color() ?? '#94a3b8', 'category' => null]
                    ],
                },
                'categories'   => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalIssues => ['technical'],
                    $lead->status === WebsiteLeadStatus::PendingDelivery  => ['shipment_delay'],
                    $lead->status === WebsiteLeadStatus::SuccessfulLead || $lead->status === WebsiteLeadStatus::LostInterest => ['resolved'],
                    default => [],
                },
                'occurrence_label' => $lead->techSupportCase?->occurrence_label,
                'category'    => match (true) {
                    $lead->status === WebsiteLeadStatus::TechnicalIssues => 'technical',
                    $lead->status === WebsiteLeadStatus::PendingDelivery  => 'shipment_delay',
                    $lead->status === WebsiteLeadStatus::SuccessfulLead || $lead->status === WebsiteLeadStatus::LostInterest => 'resolved',
                    default => null,
                },
            ]);
        });

        // Only open handler history rows + latest order (not full order history).
        EbayCustomerRecord::query()
            ->select([
                'id', 'customer_id', 'tab_type', 'buyer_name', 'username', 'email', 'phone',
                'shipment_delay', 'shipment_delivered', 'negative_feedback_causes', 'created_at',
                'negative_feedback_resolved',
            ])
            ->with([
                'customer:id,email,phone,lifetime_value',
                'handlerHistory' => fn ($q) => $q->whereNull('ended_at')->with('user:id,name'),
                'techSupportCase',
                'latestOrder',
                'orders',
            ])
            ->get()
            ->each(function (EbayCustomerRecord $record) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($record->email, $record->phone, 'ebay-' . $record->id, $record->customer_id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);

            $hasLogisticCause = in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES], true)
                && in_array('Logistic issues', $record->negative_feedback_causes ?? [], true);
            $hasLogisticIssue = ($record->shipment_delay || $hasLogisticCause) && ! $record->shipment_delivered;
            $isNegativeFeedback = in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES], true) && ! $record->negative_feedback_resolved;
            $isTechnical = $record->tab_type === EbayCustomerRecord::TAB_TECHNICAL;
            $isResolved = $record->tab_type === EbayCustomerRecord::TAB_RESOLVED || ($record->negative_feedback_resolved && ! $hasLogisticIssue);
            $isReturnReceived = $record->tab_type === EbayCustomerRecord::TAB_RETURN_RECEIVED;

            $badges = [];
            $categories = [];

            if ($isReturnReceived) {
                $badges[] = ['label' => 'Return Received', 'color' => '#10b981', 'category' => 'resolved'];
                $categories[] = 'resolved';
            } elseif ($isResolved) {
                if ($record->shipment_delivered) {
                    $badges[] = ['label' => 'Delivered', 'color' => EbayCustomerRecord::DELIVERED_COLOR, 'category' => 'delivered'];
                    $categories[] = 'delivered';
                } else {
                    $badges[] = ['label' => 'Resolved', 'color' => '#0ea5e9', 'category' => 'resolved'];
                    $categories[] = 'resolved';
                }
            } else {
                if ($hasLogisticIssue) {
                    $badges[] = ['label' => 'Logistic issues', 'color' => EbayCustomerRecord::LOGISTIC_ISSUES_COLOR, 'category' => 'shipment_delay'];
                    $categories[] = 'shipment_delay';
                }
                if ($isNegativeFeedback) {
                    $badges[] = ['label' => EbayCustomerRecord::tabs()[$record->tab_type] ?? 'Negative feedback', 'color' => EbayCustomerRecord::tabColor($record->tab_type), 'category' => 'negative_feedback'];
                    $categories[] = 'negative_feedback';
                }
                if ($isTechnical) {
                    $badges[] = ['label' => 'Technical issues', 'color' => '#8b5cf6', 'category' => 'technical'];
                    $categories[] = 'technical';
                }
                if ($record->shipment_delivered && empty($badges)) {
                    $badges[] = ['label' => 'Delivered', 'color' => EbayCustomerRecord::DELIVERED_COLOR, 'category' => 'delivered'];
                    $categories[] = 'delivered';
                }
                if (empty($badges)) {
                    $label = EbayCustomerRecord::tabs()[$record->tab_type] ?? $record->tab_type;
                    $color = EbayCustomerRecord::tabColor($record->tab_type);
                    $badges[] = ['label' => $label, 'color' => $color, 'category' => null];
                }
            }

            $primaryBadge = $badges[0];

            $ebayValue = (float) ($record->orders->sum('total_amount') ?: ($record->customer?->lifetime_value ?? 0));

            $out->push([
                'source'      => 'eBay',
                'source_icon' => '🛒',
                'source_color'=> '#f59e0b',
                'id'          => $record->id,
                'name'        => $record->buyer_name ?: $record->username,
                'email'       => $record->email,
                'phone'       => $record->phone,
                'lifetime_value' => $ebayValue,
                'status_label'=> $primaryBadge['label'],
                'status_color'=> $primaryBadge['color'],
                'status_badges' => $badges,
                'categories'  => $categories,
                'occurrence_label' => $record->techSupportCase?->occurrence_label,
                'handler'     => $record->current_handler?->name,
                'handler_id'  => $record->current_handler?->id,
                'link'        => route('crm.ebay.customers.show', $record),
                // latestOrder is the most recent purchase — null if none logged yet.
                'created_date'  => $record->created_at,
                'purchase_date' => $record->latestOrder?->ordered_at,
                'issue_date'    => null,
                'category'    => $categories[0] ?? null,
            ]);
        });

        ShipmentCustomer::query()
            ->select([
                'id', 'shipment_id', 'customer_id', 'recipient_name', 'recipient_email',
                'recipient_phone', 'status', 'notes', 'created_at',
            ])
            ->with([
                'customer:id,email,phone,lifetime_value',
                'shipment:id,created_at',
            ])
            ->latest('created_at')
            ->get()
            ->each(function (ShipmentCustomer $sc) use (&$out, $keysFor, $anySeen, $reserve) {
                $k = $keysFor($sc->recipient_email, $sc->recipient_phone, 'shipment-' . $sc->id, $sc->customer_id);
                if ($anySeen($k)) {
                    return;
                }
                $reserve($k);
                $statusLabel = match ($sc->status) {
                    ShipmentCustomer::STATUS_PROBLEM     => 'Logistic issues',
                    ShipmentCustomer::STATUS_IN_TRANSIT  => 'In Transit',
                    ShipmentCustomer::STATUS_DELIVERED   => 'Delivered',
                    default                              => 'Pending',
                };
                $statusColor = match ($sc->status) {
                    ShipmentCustomer::STATUS_PROBLEM     => EbayCustomerRecord::LOGISTIC_ISSUES_COLOR,
                    ShipmentCustomer::STATUS_IN_TRANSIT  => '#3b82f6',
                    ShipmentCustomer::STATUS_DELIVERED   => EbayCustomerRecord::DELIVERED_COLOR,
                    default                              => '#64748b',
                };
                $category = match ($sc->status) {
                    ShipmentCustomer::STATUS_PROBLEM     => 'shipment_delay',
                    ShipmentCustomer::STATUS_DELIVERED   => 'delivered',
                    ShipmentCustomer::STATUS_IN_TRANSIT  => 'in_transit',
                    default                              => 'pending',
                };
                $badges = [['label' => $statusLabel, 'color' => $statusColor, 'category' => $category]];

                $issueDate = null;
                if ($sc->status === ShipmentCustomer::STATUS_PROBLEM && $sc->notes) {
                    if (preg_match('/(?:\[Issue Date:\s*|\(Issue Date:\s*)([^\]\)]+)/i', $sc->notes, $m)) {
                        $issueDate = trim($m[1]);
                    }
                }

                $out->push([
                    'source'      => 'Logistics',
                    'source_icon' => '🚚',
                    'source_color'=> '#10b981',
                    'id'          => $sc->shipment_id ?: $sc->id,
                    'name'        => $sc->recipient_name,
                    'email'       => $sc->recipient_email,
                    'phone'       => $sc->recipient_phone,
                    'lifetime_value' => (float) ($sc->customer?->lifetime_value ?? 0),
                    'status_label'=> $statusLabel,
                    'status_color'=> $statusColor,
                    'status_badges' => $badges,
                    'categories'  => [$category],
                    'handler'     => null,
                    'handler_id'  => null,
                    'link'        => match (true) {
                        (bool) $sc->customer_id => route('crm.customers.show', $sc->customer_id),
                        (bool) $sc->shipment_id  => route('crm.logistics.shipments.show', $sc->shipment_id),
                        default                  => route('crm.logistics.processTrucking'),
                    },
                    'created_date'  => $sc->shipment?->created_at ?? $sc->created_at,
                    'purchase_date' => null,
                    'issue_date'    => $issueDate,
                    'category'    => $category,
                ]);
            });

        Customer::query()
            ->select([
                'id', 'name', 'email', 'phone', 'status', 'source', 'assigned_to',
                'shipment_delay', 'shipment_delivered', 'created_at', 'lifetime_value',
            ])
            ->with([
                'assignee:id,name',
                'latestTechSupportCase',
                'shipmentCustomers' => fn ($q) => $q->select(['id', 'customer_id', 'status', 'notes', 'updated_at'])->latest('updated_at'),
            ])
            ->get()
            ->each(function (Customer $customer) use (&$out, $keysFor, $anySeen, $reserve) {
            $k = $keysFor($customer->email, $customer->phone, 'customer-' . $customer->id, $customer->id);
            if ($anySeen($k)) {
                return;
            }
            $reserve($k);
            $sourceLabel = match ($customer->source) {
                \App\Enums\CustomerSource::Ebay->value     => 'eBay',
                \App\Enums\CustomerSource::Logistic->value => 'Logistics',
                default                                     => 'Website',
            };
            $badges = [];
            $categories = [];
            $latestSc = $customer->shipmentCustomers->first();
            $scStatus = $latestSc?->status;

            if ($customer->shipment_delay || $scStatus === ShipmentCustomer::STATUS_PROBLEM) {
                $badges[] = ['label' => 'Logistic issues', 'color' => EbayCustomerRecord::LOGISTIC_ISSUES_COLOR, 'category' => 'shipment_delay'];
                $categories[] = 'shipment_delay';
            } elseif ($scStatus === ShipmentCustomer::STATUS_IN_TRANSIT) {
                $badges[] = ['label' => 'In Transit', 'color' => '#3b82f6', 'category' => 'in_transit'];
                $categories[] = 'in_transit';
            } elseif ($customer->shipment_delivered || $scStatus === ShipmentCustomer::STATUS_DELIVERED) {
                $badges[] = ['label' => 'Delivered', 'color' => EbayCustomerRecord::DELIVERED_COLOR, 'category' => 'delivered'];
                $categories[] = 'delivered';
            }

            if (empty($badges)) {
                $badges[] = [
                    'label' => $customer->status?->label() ?? (string) $customer->status,
                    'color' => $customer->status?->color() ?? '#0ea5e9',
                    'category' => 'resolved',
                ];
                $categories[] = 'resolved';
            }

            $issueDate = null;
            if ($customer->shipment_delay) {
                $problemSc = $customer->shipmentCustomers->firstWhere('status', ShipmentCustomer::STATUS_PROBLEM);
                if ($problemSc && $problemSc->notes) {
                    if (preg_match('/(?:\[Issue Date:\s*|\(Issue Date:\s*)([^\]\)]+)/i', $problemSc->notes, $m)) {
                        $issueDate = trim($m[1]);
                    }
                }
            }

            $out->push([
                'source'      => $sourceLabel,
                'source_icon' => match ($sourceLabel) { 'eBay' => '🛒', 'Logistics' => '🚚', default => '🌐' },
                'source_color'=> match ($sourceLabel) { 'eBay' => '#f59e0b', 'Logistics' => '#10b981', default => '#3b82f6' },
                'id'          => $customer->id,
                'name'        => $customer->name,
                'email'       => $customer->email,
                'phone'       => $customer->phone,
                'lifetime_value' => (float) ($customer->lifetime_value ?? 0),
                'status_label'=> $badges[0]['label'],
                'status_color'=> $badges[0]['color'],
                'status_badges' => $badges,
                'categories'  => $categories,
                'occurrence_label' => $customer->latestTechSupportCase?->occurrence_label,
                'handler'     => $customer->assignee?->name,
                'handler_id'  => $customer->assigned_to,
                'link'        => route('crm.customers.show', $customer),
                'created_date'  => $customer->created_at,
                'purchase_date' => $customer->latestOrder?->ordered_at,
                'issue_date'    => $issueDate,
                'category'    => $categories[0] ?? null,
            ]);
        });

        // Serialize dates as ISO strings + integer timestamps + lowercase
        // search fields so list pages can filter/sort without re-parsing.
        return $out->map(function (array $row) {
            foreach (['created_date' => 'created_ts', 'purchase_date' => 'purchase_ts'] as $dateKey => $tsKey) {
                $value = $row[$dateKey] ?? null;
                if ($value instanceof \DateTimeInterface) {
                    $row[$tsKey] = $value->getTimestamp();
                    $row[$dateKey] = $value->format('c');
                } elseif (is_string($value) && $value !== '') {
                    try {
                        $parsed = \Illuminate\Support\Carbon::parse($value);
                        $row[$tsKey] = $parsed->getTimestamp();
                        $row[$dateKey] = $parsed->format('c');
                    } catch (\Throwable) {
                        $row[$tsKey] = null;
                        $row[$dateKey] = null;
                    }
                } else {
                    $row[$tsKey] = null;
                    $row[$dateKey] = null;
                }
            }

            $row['name_l'] = strtolower((string) ($row['name'] ?? ''));
            $row['email_l'] = strtolower((string) ($row['email'] ?? ''));
            $row['phone_l'] = strtolower((string) ($row['phone'] ?? ''));

            return $row;
        })->values()->all();
    }

    /**
     * Deduplicated total customer count, for the Dashboard KPI tile.
     * Cached briefly — aggregate only, no PII in the cache value.
     */
    public function dedupedCustomerCount(): int
    {
        return (int) Cache::remember('crm.deduped_customer_count', 86400, function () {
            // Use base directory (already cached) — avoid a second full rebuild.
            return $this->baseUnifiedDirectory()->count();
        });
    }
}
