<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerStatusHistory;

class UniversalStatusSyncService
{
    /**
     * Syncs logistics statuses (from ShipmentCustomer or MachineReturn)
     * back to the parent Customer and original sources.
     */
    public static function syncLogisticStatus(Customer $customer, string $status): void
    {
        // 1. Sync to Customer
        $customerStatus = CustomerStatus::tryFrom($status);
        if ($customerStatus) {
            $customer->update(['status' => $customerStatus]);
        }

        // 2. Sync to Lead (if any)
        $leadStatus = match ($status) {
            'pickup_arranged' => WebsiteLeadStatus::InTransit,
            'in_transit'      => WebsiteLeadStatus::InTransit,
            'in_delivery'     => WebsiteLeadStatus::InDelivery,
            'delivered'       => WebsiteLeadStatus::Delivered,
            'received'        => WebsiteLeadStatus::Delivered, // For return machines
            'logistic_delay'  => WebsiteLeadStatus::DelayedShipment,
            'problem'         => WebsiteLeadStatus::DelayedShipment,
            default           => null,
        };

        if ($leadStatus) {
            foreach ($customer->leads as $lead) {
                $lead->updateQuietly(['status' => $leadStatus]);
            }
        }

        // 3. Sync to eBay Records (if any)
        $ebayTab = match ($status) {
            'delivered' => EbayCustomerRecord::TAB_RESOLVED,
            'received'  => EbayCustomerRecord::TAB_RESOLVED,
            default     => EbayCustomerRecord::TAB_TECHNICAL,
        };

        foreach ($customer->ebayCustomerRecords as $ebayRecord) {
            $ebayRecord->updateQuietly(['tab_type' => $ebayTab]);
            
            EbayCustomerStatusHistory::create([
                'ebay_customer_record_id' => $ebayRecord->id,
                'status'                  => $ebayTab,
                'changed_by'              => auth()->id() ?? 1,
                'changed_at'              => now(),
            ]);
        }
    }
}
