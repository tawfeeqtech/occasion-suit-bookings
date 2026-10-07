<?php

namespace App\Filament\Pages;

use App\Models\BookingPayment;
use App\Services\FinancialReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class FinancialReports extends Page
{
    protected string $view = 'filament.pages.financial-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'التقارير المالية';

    protected static ?string $title = 'التقارير المالية والإيرادات';

    protected static ?int $navigationSort = 3;

    public string $period = 'this_month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    /**
     * @var array<string, mixed>
     */
    public array $summary = [];

    /**
     * Strict RBAC: Staff are barred from viewing financial reports. Only Owners and System Admins.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isOwner() || $user->isSystemAdmin());
    }

    public function mount(): void
    {
        $this->startDate = Carbon::today()->startOfMonth()->toDateString();
        $this->endDate = Carbon::today()->endOfMonth()->toDateString();
        $this->loadReport();
    }

    public function updatedPeriod(string $value): void
    {
        if ($value === 'today') {
            $this->startDate = Carbon::today()->toDateString();
            $this->endDate = Carbon::today()->toDateString();
        } elseif ($value === 'this_week') {
            $this->startDate = Carbon::today()->startOfWeek()->toDateString();
            $this->endDate = Carbon::today()->endOfWeek()->toDateString();
        } elseif ($value === 'this_month') {
            $this->startDate = Carbon::today()->startOfMonth()->toDateString();
            $this->endDate = Carbon::today()->endOfMonth()->toDateString();
        }

        $this->loadReport();
    }

    public function filterCustomDates(): void
    {
        $this->period = 'custom';
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $service = app(FinancialReportingService::class);
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        $this->summary = $service->getReportSummary($start, $end);
    }

    /**
     * @return Collection<int, BookingPayment>
     */
    public function getRecentPaymentsProperty()
    {
        return BookingPayment::query()
            ->with(['booking', 'recorder'])
            ->latest('created_at')
            ->take(10)
            ->get();
    }
}
