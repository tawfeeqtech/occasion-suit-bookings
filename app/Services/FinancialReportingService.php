<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\Carbon;

class FinancialReportingService
{
    /**
     * Get aggregated financial report summary for a specific date range.
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     currency: string,
     *     total_revenue: float,
     *     total_receivables: float,
     *     penalties_collected: float,
     *     revenue_by_method: array<string, float>,
     *     revenue_by_type: array<string, float>,
     *     bookings_count: int,
     *     total_contract_value: float
     * }
     */
    public function getReportSummary(Carbon $startDate, Carbon $endDate, ?string $tenantId = null): array
    {
        $resolvedTenantId = $tenantId ?? TenantContext::getTenantId();

        $currency = 'ILS';
        if ($resolvedTenantId) {
            $tenant = Tenant::find($resolvedTenantId);
            $currency = $tenant?->settings['currency'] ?? 'ILS';
        }

        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        // 1. Total revenue collected in date range
        $paymentsQuery = BookingPayment::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($resolvedTenantId) {
            $paymentsQuery->where('tenant_id', $resolvedTenantId);
        }

        $payments = $paymentsQuery->get();
        $totalRevenue = (float) $payments->sum('amount');

        // 2. Revenue breakdown by payment method
        $methods = ['cash', 'palpay', 'jawwal_pay', 'bank_transfer'];
        $revenueByMethod = [];
        foreach ($methods as $method) {
            $revenueByMethod[$method] = (float) $payments->where('method', $method)->sum('amount');
        }

        // 3. Revenue breakdown by payment type
        $types = ['advance', 'final_payment', 'penalty'];
        $revenueByType = [];
        foreach ($types as $type) {
            $revenueByType[$type] = (float) $payments->where('type', $type)->sum('amount');
        }

        $penaltiesCollected = $revenueByType['penalty'] ?? 0.00;

        // 4. Pending receivables (outstanding balance on active/pending bookings)
        $bookingsQuery = Booking::query()
            ->whereIn('status', ['active', 'damage_pending', 'overdue']);

        if ($resolvedTenantId) {
            $bookingsQuery->where('tenant_id', $resolvedTenantId);
        }

        $totalReceivables = (float) $bookingsQuery->sum('remaining_balance');

        // 5. Bookings created during this period
        $createdBookingsQuery = Booking::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($resolvedTenantId) {
            $createdBookingsQuery->where('tenant_id', $resolvedTenantId);
        }

        $bookingsCount = $createdBookingsQuery->count();
        $totalContractValue = (float) $createdBookingsQuery->sum('total_fee');

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'currency' => $currency,
            'total_revenue' => $totalRevenue,
            'total_receivables' => $totalReceivables,
            'penalties_collected' => $penaltiesCollected,
            'revenue_by_method' => $revenueByMethod,
            'revenue_by_type' => $revenueByType,
            'bookings_count' => $bookingsCount,
            'total_contract_value' => $totalContractValue,
        ];
    }

    /**
     * Get daily summary for a given date (defaults to today).
     */
    public function getDailySummary(?Carbon $date = null, ?string $tenantId = null): array
    {
        $targetDate = $date ?? Carbon::today();

        return $this->getReportSummary($targetDate, $targetDate, $tenantId);
    }

    /**
     * Get weekly summary for current week.
     */
    public function getWeeklySummary(?Carbon $date = null, ?string $tenantId = null): array
    {
        $targetDate = $date ?? Carbon::today();
        $startOfWeek = $targetDate->copy()->startOfWeek();
        $endOfWeek = $targetDate->copy()->endOfWeek();

        return $this->getReportSummary($startOfWeek, $endOfWeek, $tenantId);
    }

    /**
     * Get monthly summary for current month.
     */
    public function getMonthlySummary(?Carbon $date = null, ?string $tenantId = null): array
    {
        $targetDate = $date ?? Carbon::today();
        $startOfMonth = $targetDate->copy()->startOfMonth();
        $endOfMonth = $targetDate->copy()->endOfMonth();

        return $this->getReportSummary($startOfMonth, $endOfMonth, $tenantId);
    }
}
