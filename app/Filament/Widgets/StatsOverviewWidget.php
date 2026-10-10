<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Item;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected int|array|null $columns = [
        '@xl' => 5,
        '@lg' => 3,
        '@md' => 2,
        '!@md' => 1,
    ];

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $user = auth()->user();

        // 1. Today's pickups
        $todayPickupsCount = Booking::whereDate('pickup_date', $today)
            ->whereIn('status', ['active', 'damage_pending', 'overdue'])
            ->count();

        // 2. Today's expected returns
        $todayReturnsCount = Booking::whereDate('return_date', $today)
            ->whereIn('status', ['active', 'damage_pending', 'overdue'])
            ->count();

        // 3. Items in cleaning
        $itemsInCleaningCount = Item::where('status', 'cleaning')->count();

        $stats = [
            Stat::make('تسليمات اليوم المجدولة', $todayPickupsCount)
                ->description('حجوزات بانتظار تسليمها للعملاء')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('primary'),

            Stat::make('عوائد واستلامات اليوم', $todayReturnsCount)
                ->description('حجوزات متوقع استلامها وفحصها')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info'),

            Stat::make('قطع قيد التنظيف والتعقيم', $itemsInCleaningCount)
                ->description('في فترة الـ Buffer التلقائية')
                ->icon(Heroicon::OutlinedSparkles)
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
        ];

        // Strict RBAC: Only Shop Owner and System Admin see financial stats
        if ($user !== null && ($user->isOwner() || $user->isSystemAdmin())) {
            $totalReceivables = (float) Booking::whereIn('status', ['active', 'damage_pending', 'overdue'])->sum('remaining_balance');
            $totalRevenue = (float) BookingPayment::sum('amount');

            $stats[] = Stat::make('المستحقات المعلقة (الديون)', '₪ '.number_format($totalReceivables, 2))
                ->description('أرصدة إيجار وغرامات قيد التحصيل')
                ->icon(Heroicon::OutlinedBanknotes)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger');

            $stats[] = Stat::make('إجمالي الإيرادات المحصلة', '₪ '.number_format($totalRevenue, 2))
                ->description('المقبوضات الإجمالية للمتجر')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success');
        }

        return $stats;
    }
}
