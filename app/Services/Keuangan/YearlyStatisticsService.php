<?php

namespace App\Services\Keuangan;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class YearlyStatisticsService
{
    /**
     * @param  iterable<int, array<string, mixed>|object>  $records
     * @return array<string, mixed>
     */
    public function build(iterable $records, int $year, float $initialBalance = 0.0): array
    {
        $normalizedRecords = collect($records)
            ->map(fn ($record) => $this->normalizeRecord($record))
            ->filter()
            ->values();

        $months = range(1, 12);
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $byMonth = [];
        foreach ($months as $month) {
            $byMonth[$month] = [
                'pemasukan' => 0,
                'pengeluaran' => 0,
                'net' => 0,
            ];
        }

        $kategoriPengeluaran = [];
        $kategoriPemasukan = [];
        $labelKategori = [];

        foreach ($normalizedRecords as $record) {
            $month = (int) $record['tanggal']->month;
            $categoryKey = $this->categoryKey($record['kategori']);
            $labelKategori[$categoryKey] ??= mb_convert_case(
                $categoryKey,
                MB_CASE_TITLE,
                'UTF-8'
            );
            if ($record['jenis'] === 'pemasukan') {
                $byMonth[$month]['pemasukan'] += $record['nominal'];
                $kategoriPemasukan[$categoryKey] = ($kategoriPemasukan[$categoryKey] ?? 0) + $record['nominal'];
            } else {
                $byMonth[$month]['pengeluaran'] += $record['nominal'];
                $kategoriPengeluaran[$categoryKey] = ($kategoriPengeluaran[$categoryKey] ?? 0) + $record['nominal'];
            }
        }

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];
        $seriesNet = [];
        $cumulativeSaldo = [];

        $running = $initialBalance;
        foreach ($months as $month) {
            $labels[] = $monthNames[$month];
            $pemasukan = $byMonth[$month]['pemasukan'];
            $pengeluaran = $byMonth[$month]['pengeluaran'];
            $net = $pemasukan - $pengeluaran;
            $seriesPemasukan[] = $pemasukan;
            $seriesPengeluaran[] = $pengeluaran;
            $seriesNet[] = $net;
            $running += $net;
            $cumulativeSaldo[] = $running;
            $byMonth[$month]['net'] = $net;
        }

        $totalPemasukan = array_sum($seriesPemasukan);
        $totalPengeluaran = array_sum($seriesPengeluaran);
        $totalNet = $totalPemasukan - $totalPengeluaran;

        $isTahunBerjalan = $year === (int) Carbon::now()->year;
        $bulanTerisi = $this->projectionMonthCount($year);
        $avgPemasukan = $bulanTerisi ? $totalPemasukan / $bulanTerisi : 0;
        $avgPengeluaran = $bulanTerisi ? $totalPengeluaran / $bulanTerisi : 0;
        $avgNet = $bulanTerisi ? $totalNet / $bulanTerisi : 0;

        $isDefisit = $totalNet < 0;
        $rawSavingsRate = $totalPemasukan > 0 ? ($totalNet / $totalPemasukan) : 0;
        $savingsRate = max(0.0, $rawSavingsRate);
        $burnRate = $avgPengeluaran;
        $sisaBulan = max(0, 12 - $bulanTerisi);
        $proyeksiAkhirTahun = $isTahunBerjalan ? ($totalNet + ($avgNet * $sisaBulan)) : $totalNet;
        $proyeksiLabel = $isTahunBerjalan ? 'Proyeksi Akhir Tahun' : 'Total Bersih Tahunan';

        arsort($kategoriPengeluaran);
        $topKategoriPengeluaran = $this->formatTopCategories(
            array_slice($kategoriPengeluaran, 0, 5, true),
            $labelKategori
        );
        arsort($kategoriPemasukan);
        $topKategoriPemasukan = $this->formatTopCategories(
            array_slice($kategoriPemasukan, 0, 5, true),
            $labelKategori
        );

        $saran = [];
        if ($savingsRate < 0.1 && $totalPemasukan > 0 && ! $isDefisit) {
            $saran[] = 'Tingkatkan savings rate ke > 10% dengan kurangi kategori pengeluaran terbesar.';
        }
        if ($avgPemasukan < $avgPengeluaran) {
            $saran[] = 'Rata-rata pengeluaran melebihi pemasukan. Pertimbangkan penyesuaian anggaran.';
        }

        return [
            'labels' => $labels,
            'seriesPemasukan' => $seriesPemasukan,
            'seriesPengeluaran' => $seriesPengeluaran,
            'seriesNet' => $seriesNet,
            'cumulativeSaldo' => $cumulativeSaldo,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalNet' => $totalNet,
            'avgPemasukan' => $avgPemasukan,
            'avgPengeluaran' => $avgPengeluaran,
            'avgNet' => $avgNet,
            'savingsRate' => $savingsRate,
            'rawSavingsRate' => $rawSavingsRate,
            'isDefisit' => $isDefisit,
            'isTahunBerjalan' => $isTahunBerjalan,
            'proyeksiLabel' => $proyeksiLabel,
            'burnRate' => $burnRate,
            'proyeksiAkhirTahun' => $proyeksiAkhirTahun,
            'topKategoriPengeluaran' => $topKategoriPengeluaran,
            'topKategoriPemasukan' => $topKategoriPemasukan,
            'monthNames' => $monthNames,
            'saran' => $saran,
            'initialBalance' => $initialBalance,
        ];
    }

    private function projectionMonthCount(int $year): int
    {
        $now = Carbon::now();

        if ($year === (int) $now->year) {
            return max(1, min(12, (int) $now->month));
        }

        return 12;
    }

    private function categoryKey(string $category): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($category)) ?: 'Lainnya';

        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * @param  array<string, float|int>  $totals
     * @param  array<string, string>  $labels
     * @return array<string, float|int>
     */
    private function formatTopCategories(array $totals, array $labels): array
    {
        $formatted = [];
        foreach ($totals as $key => $total) {
            $formatted[$labels[$key] ?? $key] = $total;
        }

        return $formatted;
    }

    /**
     * @param  array<string, mixed>|object  $record
     * @return array<string, mixed>|null
     */
    private function normalizeRecord(array|object $record): ?array
    {
        $jenis = $this->recordValue($record, 'jenis') ?? 'pengeluaran';
        $kategori = $this->recordValue($record, 'kategori') ?? 'Lainnya';
        $nominal = (float) ($this->recordValue($record, 'nominal') ?? 0);
        $tanggal = $this->recordValue($record, 'tanggal');

        if (! $tanggal instanceof CarbonInterface) {
            try {
                $tanggal = Carbon::parse($tanggal);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return [
            'jenis' => $jenis === 'pemasukan' ? 'pemasukan' : 'pengeluaran',
            'kategori' => trim((string) $kategori) ?: 'Lainnya',
            'nominal' => $nominal,
            'tanggal' => Carbon::instance($tanggal),
        ];
    }

    /**
     * @param  array<string, mixed>|object  $record
     */
    private function recordValue(array|object $record, string $key): mixed
    {
        if (is_array($record)) {
            return $record[$key] ?? null;
        }

        return $record->{$key} ?? null;
    }
}
