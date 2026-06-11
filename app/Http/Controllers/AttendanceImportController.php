<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImportRule;
use App\Models\Employee;
use App\Models\Penalty;
use App\Models\PenaltyMember;
use App\Models\Violation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AttendanceImportController extends Controller
{
    public function index()
    {
        $violations = Violation::where('is_active', true)->orderBy('name')->get();

        $lateRules  = AttendanceImportRule::with('violation')
            ->where('type', 'late')
            ->orderBy('min_minutes')
            ->get();

        $earlyRules = AttendanceImportRule::with('violation')
            ->where('type', 'early')
            ->orderBy('min_minutes')
            ->get();

        $hasRules = $lateRules->isNotEmpty() || $earlyRules->isNotEmpty();

        return view('attendance-import.index', compact('violations', 'lateRules', 'earlyRules', 'hasRules'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $rows = $this->parseFile($request->file('file'));

        if ($rows === null) {
            return back()->withErrors(['file' => 'Không thể đọc file. Vui lòng kiểm tra định dạng.']);
        }

        // Load all active rules once
        $allRules = AttendanceImportRule::with('violation')
            ->where('is_active', true)
            ->orderBy('min_minutes')
            ->get()
            ->groupBy('type');

        $lateRules  = $allRules->get('late',  collect());
        $earlyRules = $allRules->get('early', collect());

        if ($lateRules->isEmpty() && $earlyRules->isEmpty()) {
            return back()->withErrors(['file' => 'Chưa cấu hình quy tắc ngưỡng phạt nào. Vui lòng thiết lập trước khi import.']);
        }

        $results = [];
        foreach ($rows as $i => $row) {
            $employeeCode = trim((string) ($row['ma_nhan_vien'] ?? ''));
            if (empty($employeeCode)) continue;

            $employee  = Employee::where('code', $employeeCode)->first();
            $lateMin   = (int) ($row['so_phut_di_muon'] ?? 0);
            $earlyMin  = (int) ($row['so_phut_ve_som']  ?? 0);

            $matchedLateRule  = $lateMin  > 0 ? $this->matchRule($lateRules,  $lateMin)  : null;
            $matchedEarlyRule = $earlyMin > 0 ? $this->matchRule($earlyRules, $earlyMin) : null;

            if (!$matchedLateRule && !$matchedEarlyRule) continue;

            $results[] = [
                'row_index'         => $i,
                'employee_code'     => $employeeCode,
                'employee_name'     => $row['ten'] ?? '',
                'date'              => $row['ngay'] ?? '',
                'shift'             => $row['ca_lam'] ?? '',
                'late_minutes'      => $lateMin,
                'early_minutes'     => $earlyMin,
                'employee'          => $employee,
                'matched_late_rule' => $matchedLateRule,
                'matched_early_rule'=> $matchedEarlyRule,
            ];
        }

        $token = Str::random(32);
        session(["import_preview_{$token}" => [
            'results' => array_map(function ($r) {
                return array_merge($r, [
                    'employee'          => $r['employee']?->id,
                    'matched_late_rule' => $r['matched_late_rule']?->id,
                    'matched_early_rule'=> $r['matched_early_rule']?->id,
                ]);
            }, $results),
        ]]);

        return view('attendance-import.preview', compact('results', 'token'));
    }

    public function confirm(Request $request)
    {
        $token = $request->input('token');
        $sessionData = session("import_preview_{$token}");

        if (!$sessionData) {
            return redirect()->route('attendance-import.index')
                ->withErrors(['error' => 'Phiên import đã hết hạn. Vui lòng thực hiện lại.']);
        }

        $selectedRows = $request->input('selected_rows', []);
        if (empty($selectedRows)) {
            return back()->withErrors(['error' => 'Vui lòng chọn ít nhất một dòng để tạo phiếu phạt.']);
        }

        $results = $sessionData['results'];
        $created = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($selectedRows as $key) {
                [$rowIndex, $type] = explode('_', $key, 2);
                $row = $results[(int) $rowIndex] ?? null;
                if (!$row || !$row['employee']) { $skipped++; continue; }

                $employee = Employee::find($row['employee']);
                if (!$employee) { $skipped++; continue; }

                $ruleId = $type === 'late' ? $row['matched_late_rule'] : $row['matched_early_rule'];
                if (!$ruleId) { $skipped++; continue; }

                $rule = AttendanceImportRule::with('violation')->find($ruleId);
                if (!$rule || !$rule->violation) { $skipped++; continue; }

                $count = Penalty::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count() + 1;
                $code = 'PNL-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

                $minutes   = $type === 'late' ? $row['late_minutes'] : $row['early_minutes'];
                $typeLabel = $type === 'late' ? "Đi trễ {$minutes} phút" : "Về sớm {$minutes} phút";
                $dateStr   = $row['date']  ? ' — Ngày: ' . $row['date']  : '';
                $shiftStr  = $row['shift'] ? ' (' . $row['shift'] . ')'  : '';
                $ruleLabel = $rule->label  ? " [{$rule->label}]"          : '';

                $penalty = Penalty::create([
                    'code'                  => $code,
                    'created_by'            => auth()->id(),
                    'employee_id'           => $employee->id,
                    'violation_id'          => $rule->violation->id,
                    'description'           => "[Import chấm công]{$ruleLabel} {$typeLabel}{$dateStr}{$shiftStr}",
                    'status'                => 'pending',
                    'total_points_deducted' => $rule->violation->points_deducted,
                    'total_money_deducted'  => $rule->violation->money_deducted ?? 0,
                ]);

                activity()->causedBy(auth()->user())
                    ->performedOn($penalty)
                    ->inLog('penalty')
                    ->withProperties([
                        'code'            => $penalty->code,
                        'employee_name'   => $employee->name,
                        'employee_code'   => $employee->code,
                        'violation'       => $rule->violation->name,
                        'points_deducted' => $penalty->total_points_deducted,
                        'rule_label'      => $rule->label,
                        'minutes'         => $minutes,
                        'source'          => 'attendance_import',
                    ])
                    ->log("Tạo phiếu phạt {$penalty->code} từ import chấm công — {$employee->name} — {$typeLabel}");

                app(NotificationService::class)->notifyPenaltyCreated($penalty);
                $created++;
            }

            DB::commit();
            session()->forget("import_preview_{$token}");

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }

        return redirect()->route('penalties.index')
            ->with('success', "Import thành công! Đã tạo {$created} phiếu phạt" . ($skipped ? ", bỏ qua {$skipped} dòng." : '.'));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function matchRule(\Illuminate\Support\Collection $rules, int $minutes): ?AttendanceImportRule
    {
        // Find highest min_minutes rule that still covers `minutes`
        return $rules
            ->filter(fn($r) => $r->min_minutes <= $minutes
                && ($r->max_minutes === null || $r->max_minutes >= $minutes))
            ->sortByDesc('min_minutes')
            ->first();
    }

    private function parseFile($uploadedFile): ?array
    {
        $ext = strtolower($uploadedFile->getClientOriginalExtension());

        if ($ext === 'csv') {
            return $this->parseCsv($uploadedFile->getRealPath());
        }

        try {
            $spreadsheet = IOFactory::load($uploadedFile->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable) {
            return null;
        }

        return $this->normalizeRows($rows);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $this->normalizeRows($rows);
    }

    private function normalizeRows(array $rawRows): array
    {
        $headerIndex = null;
        foreach ($rawRows as $i => $row) {
            foreach ($row as $cell) {
                $cell = mb_strtolower(trim((string) $cell));
                if (str_contains($cell, 'mã nhân viên') || str_contains($cell, 'ma nhan vien')) {
                    $headerIndex = $i;
                    break 2;
                }
            }
        }

        if ($headerIndex === null) return [];

        $headers = array_map(fn($h) => mb_strtolower(trim((string) $h)), $rawRows[$headerIndex]);

        $colMap = [];
        foreach ($headers as $i => $h) {
            if (str_contains($h, 'mã nhân viên') || str_contains($h, 'ma nhan vien'))      $colMap['ma_nhan_vien']    = $i;
            elseif (str_contains($h, 'tên') || $h === 'ten')                                $colMap['ten']             = $i;
            elseif (str_contains($h, 'ngày') || $h === 'ngay')                              $colMap['ngay']            = $i;
            elseif (str_contains($h, 'ca làm') || str_contains($h, 'ca lam'))               $colMap['ca_lam']          = $i;
            elseif (str_contains($h, 'phút đi muộn') || str_contains($h, 'phut di muon'))   $colMap['so_phut_di_muon'] = $i;
            elseif (str_contains($h, 'lần đi muộn') || str_contains($h, 'lan di muon'))     $colMap['so_lan_di_muon']  = $i;
            elseif (str_contains($h, 'phút về sớm') || str_contains($h, 'phut ve som'))     $colMap['so_phut_ve_som']  = $i;
            elseif (str_contains($h, 'lần về sớm') || str_contains($h, 'lan ve som'))       $colMap['so_lan_ve_som']   = $i;
        }

        $result = [];
        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $raw = $rawRows[$i];
            $row = [];
            foreach ($colMap as $key => $col) {
                $row[$key] = $raw[$col] ?? null;
            }
            if (empty(trim((string) ($row['ma_nhan_vien'] ?? '')))) continue;

            if (!empty($row['ngay'])) {
                if (is_numeric($row['ngay'])) {
                    try {
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['ngay']);
                        $row['ngay'] = $dt->format('d/m/Y');
                    } catch (\Throwable) {}
                } elseif ($row['ngay'] instanceof \DateTime) {
                    $row['ngay'] = $row['ngay']->format('d/m/Y');
                }
            }

            $result[] = $row;
        }

        return $result;
    }
}
