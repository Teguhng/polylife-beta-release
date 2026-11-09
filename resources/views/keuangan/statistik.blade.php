@extends('layouts.app')

@section('page_title', 'Statistik Keuangan (Beta)')

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $statistikAction = $guestMode ? route('guest.keuangan.statistik') : route('keuangan.statistik');
    $tahunOptions = $tahunOptions ?? range(now()->year, now()->year - 5);
    $chartPayload = [
        'labels' => $labels,
        'pemasukan' => $seriesPemasukan,
        'pengeluaran' => $seriesPengeluaran,
        'net' => $seriesNet,
        'saldo' => $cumulativeSaldo,
    ];
@endphp
<div class="space-y-6">
    {{-- Sub-Tabs Navigation --}}
    <div class="flex items-center justify-between">
        @include('keuangan.partials.nav-tabs', ['activeTab' => 'statistik', 'guestMode' => $guestMode])
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-slate-100">Ringkasan Tahunan</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Analisis keuangan dan performa anggaran tahun <span class="font-semibold text-gray-800 dark:text-slate-200">{{ $tahun }}</span></p>
            </div>
            <form method="GET" action="{{ $statistikAction }}" class="flex items-center gap-2">
                <label for="tahun" class="text-sm text-gray-600 dark:text-slate-300">Tahun</label>
                <select id="tahun" name="tahun" class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    @foreach($tahunOptions as $tahunOption)
                        <option value="{{ $tahunOption }}" {{ $tahun == $tahunOption ? 'selected' : '' }}>{{ $tahunOption }}</option>
                    @endforeach
                </select>
                <button class="px-3 py-2 rounded-lg bg-gray-800 text-white hover:bg-black dark:bg-slate-700 dark:hover:bg-slate-600">Terapkan</button>
                <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-rose-500 text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-60"
                    data-statistik-print
                    disabled>
                    <span data-statistik-print-label>Menyiapkan PDF</span>
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="rounded-xl p-4 bg-green-50 border border-green-100 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-sm text-green-700 dark:text-emerald-300">Total Pemasukan</p>
                <p class="text-2xl font-bold text-green-900 dark:text-emerald-100">Rp {{ number_format($totalPemasukan,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-rose-50 border border-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10">
                <p class="text-sm text-rose-700 dark:text-rose-300">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-rose-900 dark:text-rose-100">Rp {{ number_format($totalPengeluaran,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-indigo-50 border border-indigo-100 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                <p class="text-sm text-indigo-700 dark:text-indigo-300">Saldo (Netto)</p>
                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">Rp {{ number_format($totalNet,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 {{ !empty($isDefisit) ? 'bg-rose-50 border border-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10' : 'bg-amber-50 border border-amber-100 dark:border-amber-500/20 dark:bg-amber-500/10' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm {{ !empty($isDefisit) ? 'text-rose-700 dark:text-rose-300' : 'text-amber-700 dark:text-amber-300' }}">Savings Rate</p>
                    @if(!empty($isDefisit))
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">Defisit</span>
                    @endif
                </div>
                <p class="text-2xl font-bold {{ !empty($isDefisit) ? 'text-rose-900 dark:text-rose-100' : 'text-amber-900 dark:text-amber-100' }}">{{ number_format($savingsRate * 100, 1, ',', '.') }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div class="rounded-xl p-4 bg-gray-50 border border-gray-100 dark:bg-slate-800/80 dark:border-slate-700">
                <p class="text-sm text-gray-700 dark:text-slate-400">Rata-rata Pemasukan / bulan</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-slate-100">Rp {{ number_format($avgPemasukan,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gray-50 border border-gray-100 dark:bg-slate-800/80 dark:border-slate-700">
                <p class="text-sm text-gray-700 dark:text-slate-400">Rata-rata Pengeluaran / bulan</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-slate-100">Rp {{ number_format($avgPengeluaran,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gray-50 border border-gray-100 dark:bg-slate-800/80 dark:border-slate-700">
                <p class="text-sm text-gray-700 dark:text-slate-400">{{ $proyeksiLabel ?? 'Proyeksi Akhir Tahun' }}</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-slate-100">Rp {{ number_format($proyeksiAkhirTahun,0,',','.') }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-slate-100">Tren Bulanan</h3>
        <canvas id="chartKeuangan" height="120"></canvas>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-slate-100">Top Kategori Pengeluaran</h3>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800 text-gray-500 dark:text-slate-400">
                        <th class="py-2 pr-4">Kategori</th>
                        <th class="py-2 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($topKategoriPengeluaran as $kat => $val)
                    <tr class="text-gray-800 dark:text-slate-200">
                        <td class="py-2 pr-4">{{ $kat }}</td>
                        <td class="py-2 pr-4 text-right font-medium">Rp {{ number_format($val,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="py-4 text-center text-gray-500 dark:text-slate-400">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-slate-100">Top Kategori Pemasukan</h3>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800 text-gray-500 dark:text-slate-400">
                        <th class="py-2 pr-4">Kategori</th>
                        <th class="py-2 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($topKategoriPemasukan as $kat => $val)
                    <tr class="text-gray-800 dark:text-slate-200">
                        <td class="py-2 pr-4">{{ $kat }}</td>
                        <td class="py-2 pr-4 text-right font-medium">Rp {{ number_format($val,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="py-4 text-center text-gray-500 dark:text-slate-400">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(!empty($saran))
    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6 dark:border-indigo-500/20 dark:bg-indigo-500/10">
        <h3 class="text-lg font-semibold mb-2 text-indigo-900 dark:text-indigo-200">Saran Otomatis</h3>
        <ul class="list-disc pl-5 space-y-1 text-indigo-900 dark:text-indigo-300">
            @foreach($saran as $s)
                <li>{{ $s }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

@push('styles')
<style>
@media print {
    header, nav, aside, [role="button"], button, form[action*="statistik"] button[type="submit"], .no-print { display: none !important; }
    main { padding: 0 !important; }
    .border, .shadow-sm, .rounded-2xl { box-shadow: none !important; }
    .bg-white { background: #fff !important; }
    @page { size: A4; margin: 12mm; }
}
</style>
@endpush

@push('scripts')
<script type="application/json" id="keuangan-statistik-data">
{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
@vite('resources/js/keuangan-statistik.js')
@endpush
@endsection
