<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KeuanganBudgetRequest;
use App\Models\KeuanganBudget;
use App\Services\Keuangan\BudgetEvaluationService;
use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KeuanganBudgetController extends Controller
{
    public function __construct(
        private readonly BudgetEvaluationService $budgetService,
        private readonly StatisticYearRange $statisticYearRange
    ) {
    }

    /**
     * Mengambil evaluasi anggaran berdasarkan bulan dan tahun.
     */
    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now(config('app.timezone'));

        $month = filter_var(
            $request->query('bulan'),
            FILTER_VALIDATE_INT
        );

        if ($month === false || $month < 1 || $month > 12) {
            $month = (int) $now->month;
        }

        $year = $this->statisticYearRange->resolve(
            $request->query('tahun'),
            $now
        );

        $evaluation = $this->budgetService->evaluateUser(
            (int) $request->user()->id,
            $month,
            $year
        );

        return response()->json([
            'data' => $evaluation,
        ]);
    }

    /**
     * Menyimpan atau memperbarui anggaran.
     */
    public function store(KeuanganBudgetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = mb_convert_case(
            preg_replace('/\s+/u', ' ', trim($validated['kategori'])) ?: 'Lainnya',
            MB_CASE_TITLE,
            'UTF-8'
        );
        $categoryKey = mb_strtolower($category, 'UTF-8');
        $query = KeuanganBudget::query()
            ->where('user_id', $request->user()->id)
            ->where('bulan', (int) $validated['bulan'])
            ->where('tahun', (int) $validated['tahun']);
        $budget = $query
            ->whereRaw('LOWER(kategori) = ?', [$categoryKey])
            ->first();
        $created = $budget === null;

        if ($budget === null) {
            $budget = KeuanganBudget::create([
                'user_id' => $request->user()->id,
                'kategori' => $category,
                'bulan' => (int) $validated['bulan'],
                'tahun' => (int) $validated['tahun'],
                'nominal_limit' => $validated['nominal_limit'],
            ]);
        } else {
            $budget->update([
                'kategori' => $category,
                'nominal_limit' => $validated['nominal_limit'],
            ]);
        }

        return response()->json([
            'message' => 'Anggaran berhasil disimpan.',
            'data' => $this->formatBudget($budget),
        ], $created ? 201 : 200);
    }

    /**
     * Menghapus anggaran milik pengguna yang sedang login.
     */
    public function destroy(Request $request, int $budget): JsonResponse
    {
        $item = KeuanganBudget::query()
            ->where('id', $budget)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $item) {
            abort(404, 'Data anggaran tidak ditemukan.');
        }

        $item->delete();

        return response()->json([
            'message' => 'Anggaran berhasil dihapus.',
        ]);
    }

    private function formatBudget(KeuanganBudget $budget): array
    {
        return [
            'id' => (int) $budget->id,
            'kategori' => $budget->kategori,
            'nominal_limit' => (float) $budget->nominal_limit,
            'bulan' => (int) $budget->bulan,
            'tahun' => (int) $budget->tahun,
        ];
    }
}
