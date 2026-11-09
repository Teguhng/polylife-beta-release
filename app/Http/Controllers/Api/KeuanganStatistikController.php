<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use App\Services\Keuangan\YearlyStatisticsService;
use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeuanganStatistikController extends Controller
{
    public function __construct(
        private readonly YearlyStatisticsService $yearlyStatisticsService,
        private readonly StatisticYearRange $statisticYearRange
    ) {
    }

    /**
     * Mengambil statistik keuangan berdasarkan tahun.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $year = $this->statisticYearRange->resolve(
            $request->query('tahun')
        );

        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $initialBalance = (float) Keuangan::where('user_id', $userId)
            ->where('tanggal', '<', $startDate)
            ->selectRaw("
                COALESCE(
                    SUM(
                        CASE
                            WHEN jenis = 'pemasukan' THEN nominal
                            ELSE -nominal
                        END
                    ),
                    0
                ) as balance
            ")
            ->value('balance');

        $records = Keuangan::where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal')
            ->get([
                'jenis',
                'kategori',
                'nominal',
                'tanggal',
            ]);

        $statistics = $this->yearlyStatisticsService->build(
            $records,
            $year,
            $initialBalance
        );

        return response()->json([
            'data' => array_merge([
                'tahun' => $year,
                'tahun_options' => $this->statisticYearRange->options(),
            ], $statistics),
        ]);
    }
}