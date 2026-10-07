<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Date Period Filter Toolbar -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$set('period', 'today')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition {{ $period === 'today' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800 text-slate-200 hover:bg-slate-700' }}">
                    اليوم
                </button>
                <button type="button" wire:click="$set('period', 'this_week')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition {{ $period === 'this_week' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800 text-slate-200 hover:bg-slate-700' }}">
                    هذا الأسبوع
                </button>
                <button type="button" wire:click="$set('period', 'this_month')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition {{ $period === 'this_month' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800 text-slate-200 hover:bg-slate-700' }}">
                    هذا الشهر
                </button>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400">من:</span>
                <input type="date" wire:model="startDate" class="bg-slate-800 text-white border border-slate-700 text-sm rounded-lg px-3 py-1.5 focus:border-amber-500">
                <span class="text-xs text-slate-400">إلى:</span>
                <input type="date" wire:model="endDate" class="bg-slate-800 text-white border border-slate-700 text-sm rounded-lg px-3 py-1.5 focus:border-amber-500">
                <button type="button" wire:click="filterCustomDates" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-500">
                    تطبيق
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Revenue -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-slate-400">إجمالي الإيرادات المحصلة</span>
                    <span class="p-2 bg-emerald-950/60 text-emerald-400 rounded-lg">₪</span>
                </div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    ₪ {{ number_format($summary['total_revenue'] ?? 0, 2) }}
                </div>
                <div class="text-xs text-emerald-400 mt-2">
                    مقبوضات الفترة المحددة
                </div>
            </div>

            <!-- Pending Receivables -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-slate-400">الديون والمستحقات المعلقة</span>
                    <span class="p-2 bg-rose-950/60 text-rose-400 rounded-lg">⏳</span>
                </div>
                <div class="text-2xl font-bold text-rose-400 tracking-tight">
                    ₪ {{ number_format($summary['total_receivables'] ?? 0, 2) }}
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    متبقيات عقود معلقة قيد التحصيل
                </div>
            </div>

            <!-- Penalties Collected -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-slate-400">الغرامات المحصلة</span>
                    <span class="p-2 bg-amber-950/60 text-amber-400 rounded-lg">⚠️</span>
                </div>
                <div class="text-2xl font-bold text-amber-400 tracking-tight">
                    ₪ {{ number_format($summary['penalties_collected'] ?? 0, 2) }}
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    تعويضات التلف والفقدان
                </div>
            </div>

            <!-- Bookings Count & Value -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-slate-400">حجوزات الفترة</span>
                    <span class="p-2 bg-sky-950/60 text-sky-400 rounded-lg">📋</span>
                </div>
                <div class="text-2xl font-bold text-sky-400 tracking-tight">
                    {{ $summary['bookings_count'] ?? 0 }} حجز
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    بقيمة عقود إجمالية: ₪ {{ number_format($summary['total_contract_value'] ?? 0, 2) }}
                </div>
            </div>
        </div>

        <!-- Breakdown Tables Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Payment Method Breakdown -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <span>💳</span> تصنيف الإيرادات حسب طريقة الدفع
                </h3>
                <div class="space-y-3">
                    @php
                        $methodLabels = [
                            'cash' => 'نقداً (كاش)',
                            'palpay' => 'PalPay (بال باي)',
                            'jawwal_pay' => 'Jawwal Pay (جوال باي)',
                            'bank_transfer' => 'تحويل بنكي',
                        ];
                    @endphp
                    @foreach ($summary['revenue_by_method'] ?? [] as $method => $amount)
                        <div class="flex items-center justify-between p-3 bg-slate-800/60 rounded-lg">
                            <span class="text-sm text-slate-300 font-medium">{{ $methodLabels[$method] ?? $method }}</span>
                            <span class="text-sm font-bold text-amber-400">₪ {{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Payment Type Breakdown -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <span>📊</span> تصنيف الإيرادات حسب نوع الدفعة
                </h3>
                <div class="space-y-3">
                    @php
                        $typeLabels = [
                            'advance' => 'دفعة مقدمة (Advance)',
                            'final_payment' => 'سداد نهائي عند الاستلام (Final Settlement)',
                            'penalty' => 'غرامات تلف وفقدان (Penalty)',
                        ];
                    @endphp
                    @foreach ($summary['revenue_by_type'] ?? [] as $type => $amount)
                        <div class="flex items-center justify-between p-3 bg-slate-800/60 rounded-lg">
                            <span class="text-sm text-slate-300 font-medium">{{ $typeLabels[$type] ?? $type }}</span>
                            <span class="text-sm font-bold text-emerald-400">₪ {{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Payments Ledger Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                <span>📑</span> سجل آخر العمليات المقبوضة
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right text-slate-300">
                    <thead class="bg-slate-800/80 text-xs text-slate-400 uppercase">
                        <tr>
                            <th class="py-3 px-4">رقم الحجز</th>
                            <th class="py-3 px-4">المبلغ</th>
                            <th class="py-3 px-4">نوع الدفعة</th>
                            <th class="py-3 px-4">طريقة الدفع</th>
                            <th class="py-3 px-4">المسؤول</th>
                            <th class="py-3 px-4">التاريخ والوقت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($this->recentPayments as $payment)
                            <tr class="hover:bg-slate-800/40">
                                <td class="py-3 px-4 font-mono font-bold text-amber-400">{{ $payment->booking?->booking_number ?? '-' }}</td>
                                <td class="py-3 px-4 font-bold text-white">₪ {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $payment->type === 'advance' ? 'bg-blue-950 text-blue-400' : ($payment->type === 'penalty' ? 'bg-rose-950 text-rose-400' : 'bg-emerald-950 text-emerald-400') }}">
                                        {{ $payment->type === 'advance' ? 'مقدم' : ($payment->type === 'penalty' ? 'غرامة' : 'سداد نهائي') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">{{ $payment->method }}</td>
                                <td class="py-3 px-4">{{ $payment->recorder?->name ?? 'موظف' }}</td>
                                <td class="py-3 px-4 text-xs text-slate-400">{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500">لا توجد عمليات مقبوضات مسجلة بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
