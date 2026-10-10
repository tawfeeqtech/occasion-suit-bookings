<x-filament-panels::page>
    @php
        $currency = $summary['currency'] ?? 'ILS';
        $methodAmounts = $summary['revenue_by_method'] ?? [];
        $typeAmounts = $summary['revenue_by_type'] ?? [];
        $methodMaximum = max(1, ...array_values($methodAmounts));
        $typeMaximum = max(1, ...array_values($typeAmounts));

        $methodLabels = [
            'cash' => 'نقداً',
            'palpay' => 'PalPay',
            'jawwal_pay' => 'Jawwal Pay',
            'bank_transfer' => 'تحويل بنكي',
        ];

        $typeLabels = [
            'advance' => 'دفعة مقدمة',
            'final_payment' => 'سداد نهائي',
            'penalty' => 'غرامة',
        ];

        $paymentTypeLabels = [
            'advance' => 'مقدم',
            'final_payment' => 'سداد نهائي',
            'penalty' => 'غرامة',
        ];

        $paymentMethodLabels = [
            'cash' => 'نقداً',
            'palpay' => 'PalPay',
            'jawwal_pay' => 'Jawwal Pay',
            'bank_transfer' => 'تحويل بنكي',
        ];
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800 shadow-sm">
            <div class="flex flex-col gap-6 p-5 sm:p-7 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl space-y-2">
                    <div class="flex items-center gap-2 text-sm font-medium text-amber-400">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span>لوحة الأداء المالي</span>
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">ملخص الأداء المالي</h2>
                    <p class="text-sm leading-6 text-slate-300 sm:text-base">
                        نظرة واضحة على الإيرادات والمستحقات وحركة الدفعات في متجرك.
                    </p>
                    @if (! empty($summary['start_date']) && ! empty($summary['end_date']))
                        <p class="text-sm text-slate-400">
                            الفترة من
                            <time datetime="{{ $summary['start_date'] }}" dir="ltr">{{ $summary['start_date'] }}</time>
                            إلى
                            <time datetime="{{ $summary['end_date'] }}" dir="ltr">{{ $summary['end_date'] }}</time>
                        </p>
                    @endif
                </div>

                <div class="w-full space-y-4 xl:max-w-2xl">
                    <div class="flex flex-wrap gap-2" role="group" aria-label="اختيار الفترة الزمنية">
                        <button
                            type="button"
                            wire:click="$set('period', 'today')"
                            aria-pressed="{{ $period === 'today' ? 'true' : 'false' }}"
                            class="min-h-11 cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 {{ $period === 'today' ? 'bg-amber-400 text-slate-950' : 'border border-slate-700 bg-slate-800 text-slate-200 hover:border-slate-600 hover:bg-slate-700' }}"
                        >
                            اليوم
                        </button>
                        <button
                            type="button"
                            wire:click="$set('period', 'this_week')"
                            aria-pressed="{{ $period === 'this_week' ? 'true' : 'false' }}"
                            class="min-h-11 cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 {{ $period === 'this_week' ? 'bg-amber-400 text-slate-950' : 'border border-slate-700 bg-slate-800 text-slate-200 hover:border-slate-600 hover:bg-slate-700' }}"
                        >
                            هذا الأسبوع
                        </button>
                        <button
                            type="button"
                            wire:click="$set('period', 'this_month')"
                            aria-pressed="{{ $period === 'this_month' ? 'true' : 'false' }}"
                            class="min-h-11 cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 {{ $period === 'this_month' ? 'bg-amber-400 text-slate-950' : 'border border-slate-700 bg-slate-800 text-slate-200 hover:border-slate-600 hover:bg-slate-700' }}"
                        >
                            هذا الشهر
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <div class="space-y-1.5">
                            <label for="report-start-date" class="block text-xs font-medium text-slate-300">من تاريخ</label>
                            <input
                                id="report-start-date"
                                type="date"
                                wire:model="startDate"
                                class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 text-sm text-white focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                            >
                        </div>
                        <div class="space-y-1.5">
                            <label for="report-end-date" class="block text-xs font-medium text-slate-300">إلى تاريخ</label>
                            <input
                                id="report-end-date"
                                type="date"
                                wire:model="endDate"
                                class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 text-sm text-white focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                            >
                        </div>
                        <button
                            type="button"
                            wire:click="filterCustomDates"
                            class="min-h-11 cursor-pointer rounded-lg bg-amber-400 px-5 py-2 text-sm font-bold text-slate-950 transition-colors duration-200 hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                        >
                            تطبيق الفترة
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="financial-summary-heading" class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h3 id="financial-summary-heading" class="text-lg font-bold text-white">المؤشرات الرئيسية</h3>
                    <p class="mt-1 text-sm text-slate-400">الأرقام المرتبطة بالفترة المحددة</p>
                </div>
                <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-medium text-slate-300">
                    العملة: {{ $currency }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                <article class="rounded-xl border border-emerald-900/70 bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-300">الإيرادات المحصلة</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-950 text-emerald-300" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m4-9.5c0-1.38-1.79-2.5-4-2.5s-4 1.12-4 2.5 1.79 2.5 4 2.5 4 1.12 4 2.5-1.79 2.5-4 2.5-4-1.12-4-2.5" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-white" dir="ltr">
                        {{ number_format($summary['total_revenue'] ?? 0, 2) }}
                        <span class="text-sm font-semibold text-slate-400">{{ $currency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-emerald-300">إجمالي الدفعات المسجلة خلال الفترة</p>
                </article>

                <article class="rounded-xl border border-rose-900/70 bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-300">المستحقات المعلقة</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-950 text-rose-300" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-white" dir="ltr">
                        {{ number_format($summary['total_receivables'] ?? 0, 2) }}
                        <span class="text-sm font-semibold text-slate-400">{{ $currency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-400">أرصدة الحجوزات النشطة قيد التحصيل</p>
                </article>

                <article class="rounded-xl border border-amber-900/70 bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-300">الغرامات المحصلة</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-950 text-amber-300" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.74 3h14.92a2 2 0 0 0 1.74-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-white" dir="ltr">
                        {{ number_format($summary['penalties_collected'] ?? 0, 2) }}
                        <span class="text-sm font-semibold text-slate-400">{{ $currency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-400">تعويضات التلف أو الفقدان</p>
                </article>

                <article class="rounded-xl border border-sky-900/70 bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-300">حجوزات الفترة</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-950 text-sky-300" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-white">
                        {{ number_format($summary['bookings_count'] ?? 0) }}
                        <span class="text-sm font-semibold text-slate-400">حجز</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-400">
                        قيمة العقود:
                        <span dir="ltr">{{ number_format($summary['total_contract_value'] ?? 0, 2) }} {{ $currency }}</span>
                    </p>
                </article>
            </div>
        </section>

        <section aria-label="تحليل الإيرادات" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="rounded-xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-white">الإيرادات حسب طريقة الدفع</h3>
                        <p class="mt-1 text-sm text-slate-400">مقارنة المقبوضات لكل قناة دفع</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-amber-300" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect width="18" height="14" x="3" y="5" rx="2" />
                            <path stroke-linecap="round" d="M3 10h18m-14 5h4" />
                        </svg>
                    </span>
                </div>

                <div class="mt-6 space-y-5">
                    @foreach ($methodAmounts as $method => $amount)
                        @php
                            $methodLabel = $methodLabels[$method] ?? $method;
                            $methodPercentage = ($amount / $methodMaximum) * 100;
                        @endphp
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                                <span class="font-medium text-slate-200">{{ $methodLabel }}</span>
                                <span class="font-semibold text-white" dir="ltr">{{ number_format($amount, 2) }} {{ $currency }}</span>
                            </div>
                            <div
                                class="h-2.5 overflow-hidden rounded-full bg-slate-800"
                                role="progressbar"
                                aria-label="نسبة {{ $methodLabel }} من أعلى طريقة دفع"
                                aria-valuemin="0"
                                aria-valuemax="{{ $methodMaximum }}"
                                aria-valuenow="{{ $amount }}"
                            >
                                <div class="h-full rounded-full bg-amber-400 transition-[width] duration-300" style="width: {{ $methodPercentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-white">الإيرادات حسب نوع الدفعة</h3>
                        <p class="mt-1 text-sm text-slate-400">توزيع المقبوضات بحسب مرحلة الدفع</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-emerald-300" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6" />
                        </svg>
                    </span>
                </div>

                <div class="mt-6 space-y-5">
                    @foreach ($typeAmounts as $type => $amount)
                        @php
                            $typeLabel = $typeLabels[$type] ?? $type;
                            $typePercentage = ($amount / $typeMaximum) * 100;
                        @endphp
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                                <span class="font-medium text-slate-200">{{ $typeLabel }}</span>
                                <span class="font-semibold text-white" dir="ltr">{{ number_format($amount, 2) }} {{ $currency }}</span>
                            </div>
                            <div
                                class="h-2.5 overflow-hidden rounded-full bg-slate-800"
                                role="progressbar"
                                aria-label="نسبة {{ $typeLabel }} من أعلى نوع دفعة"
                                aria-valuemin="0"
                                aria-valuemax="{{ $typeMaximum }}"
                                aria-valuenow="{{ $amount }}"
                            >
                                <div class="h-full rounded-full bg-emerald-400 transition-[width] duration-300" style="width: {{ $typePercentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section aria-labelledby="recent-payments-heading" class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4 sm:px-6">
                <div>
                    <h3 id="recent-payments-heading" class="text-base font-bold text-white">سجل الدفعات الأخيرة</h3>
                    <p class="mt-1 text-sm text-slate-400">أحدث 10 عمليات قبض مسجلة</p>
                </div>
                <span class="rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300">
                    آخر العمليات
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full text-right text-sm">
                    <caption class="sr-only">تفاصيل أحدث عمليات القبض المسجلة</caption>
                    <thead class="bg-slate-800/70 text-xs font-semibold text-slate-300">
                        <tr>
                            <th scope="col" class="px-5 py-3.5">رقم الحجز</th>
                            <th scope="col" class="px-5 py-3.5">المبلغ</th>
                            <th scope="col" class="px-5 py-3.5">نوع الدفعة</th>
                            <th scope="col" class="px-5 py-3.5">طريقة الدفع</th>
                            <th scope="col" class="px-5 py-3.5">المسؤول</th>
                            <th scope="col" class="px-5 py-3.5">التاريخ والوقت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($this->recentPayments as $payment)
                            @php
                                $paymentType = $paymentTypeLabels[$payment->type] ?? $payment->type;
                                $paymentMethod = $paymentMethodLabels[$payment->method] ?? $payment->method;
                                $paymentTypeColor = match ($payment->type) {
                                    'advance' => 'border-sky-900 bg-sky-950/70 text-sky-200',
                                    'penalty' => 'border-rose-900 bg-rose-950/70 text-rose-200',
                                    default => 'border-emerald-900 bg-emerald-950/70 text-emerald-200',
                                };
                            @endphp
                            <tr class="transition-colors duration-150 hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-4 font-mono font-semibold text-amber-300">
                                    {{ $payment->booking?->booking_number ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-white" dir="ltr">
                                    {{ number_format((float) $payment->amount, 2) }} {{ $currency }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $paymentTypeColor }}">
                                        {{ $paymentType }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $paymentMethod }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $payment->recorder?->name ?? 'موظف' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-400" dir="ltr">
                                    {{ $payment->created_at?->format('Y-m-d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 text-slate-400" aria-hidden="true">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m-8 4h8m-8 4h5M5 3h14a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z" />
                                        </svg>
                                    </span>
                                    <p class="mt-3 font-medium text-slate-200">لا توجد دفعات مسجلة حتى الآن</p>
                                    <p class="mt-1 text-sm text-slate-400">ستظهر عمليات القبض هنا بعد تسجيلها.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
