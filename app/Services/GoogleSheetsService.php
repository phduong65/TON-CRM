<?php

namespace App\Services;

use App\Models\Regulation;
use App\Models\RewardCategory;
use App\Models\RewardType;
use App\Models\Violation;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    protected Sheets $sheets;
    protected string $spreadsheetId;

    // Tab names
    const TAB_REGULATIONS     = 'Quy Chế';
    const TAB_VIOLATIONS      = 'Vi Phạm';
    const TAB_REWARD_CATS     = 'Danh Mục Thưởng';
    const TAB_REWARD_TYPES    = 'Loại Thưởng';

    // Headers per tab
    const HEADERS = [
        self::TAB_REGULATIONS  => ['ID', 'Tên Quy Chế', 'Mô Tả', 'Ngày Hiệu Lực', 'Trạng Thái'],
        self::TAB_VIOLATIONS   => ['ID', 'Tên Vi Phạm', 'Mô Tả', 'Mức Độ', 'Quy Chế', 'Loại Phạt', 'Điểm Trừ', 'Tiền Trừ', 'Trạng Thái'],
        self::TAB_REWARD_CATS  => ['ID', 'Tên Danh Mục', 'Mô Tả', 'Trạng Thái'],
        self::TAB_REWARD_TYPES => ['ID', 'Danh Mục', 'Tên Loại Thưởng', 'Mô Tả', 'Điểm Thưởng Mặc Định', 'Trạng Thái'],
    ];

    public function __construct()
    {
        $client = new Client();
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(base_path(config('google.service_account_json_path')));

        $this->sheets        = new Sheets($client);
        $this->spreadsheetId = config('google.sheet_id');
    }

    // ── Setup: đảm bảo 4 tab tồn tại với đúng tên ──────────────────────────

    public function setupTabs(): array
    {
        $existing  = $this->getExistingSheetTitles();
        $required  = array_keys(self::HEADERS);
        $requests  = [];

        foreach ($required as $title) {
            if (!in_array($title, $existing)) {
                $requests[] = new Request([
                    'addSheet' => ['properties' => ['title' => $title]],
                ]);
            }
        }

        if (!empty($requests)) {
            $this->sheets->spreadsheets->batchUpdate(
                $this->spreadsheetId,
                new BatchUpdateSpreadsheetRequest(['requests' => $requests])
            );
        }

        // Write headers for each tab
        foreach (self::HEADERS as $tab => $headers) {
            $this->writeRow($tab, 1, $headers, true);
        }

        return ['tabs_created' => count($requests), 'tabs' => $required];
    }

    // ── Push DB → Sheet ──────────────────────────────────────────────────────

    public function pushAll(): array
    {
        $this->setupTabs();

        $stats = [
            'regulations'    => $this->pushRegulations(),
            'violations'     => $this->pushViolations(),
            'reward_cats'    => $this->pushRewardCategories(),
            'reward_types'   => $this->pushRewardTypes(),
        ];

        return $stats;
    }

    public function pushRegulations(): int
    {
        $rows = Regulation::orderBy('id')->get()->map(fn($r) => [
            $r->id,
            $r->name,
            $r->description ?? '',
            $r->effective_date ? $r->effective_date->format('d/m/Y') : '',
            $r->is_active ? 'Hoạt động' : 'Ngừng',
        ])->toArray();

        return $this->writeTabData(self::TAB_REGULATIONS, $rows);
    }

    public function pushViolations(): int
    {
        $rows = Violation::with('regulation')->orderBy('id')->get()->map(fn($v) => [
            $v->id,
            $v->name,
            $v->description ?? '',
            $this->severityLabel($v->severity),
            $v->regulation ? $v->regulation_id . ' - ' . $v->regulation->name : '',
            $this->penaltyTypeLabel($v->penalty_type),
            $v->points_deducted,
            $v->money_deducted,
            $v->is_active ? 'Hoạt động' : 'Ngừng',
        ])->toArray();

        return $this->writeTabData(self::TAB_VIOLATIONS, $rows);
    }

    public function pushRewardCategories(): int
    {
        $rows = RewardCategory::orderBy('id')->get()->map(fn($c) => [
            $c->id,
            $c->name,
            $c->description ?? '',
            $c->is_active ? 'Hoạt động' : 'Ngừng',
        ])->toArray();

        return $this->writeTabData(self::TAB_REWARD_CATS, $rows);
    }

    public function pushRewardTypes(): int
    {
        $rows = RewardType::with('category')->orderBy('id')->get()->map(fn($t) => [
            $t->id,
            $t->category ? $t->reward_category_id . ' - ' . $t->category->name : '',
            $t->name,
            $t->description ?? '',
            $t->default_points,
            $t->is_active ? 'Hoạt động' : 'Ngừng',
        ])->toArray();

        return $this->writeTabData(self::TAB_REWARD_TYPES, $rows);
    }

    // ── Import Sheet → DB ────────────────────────────────────────────────────

    public function importAll(): array
    {
        return [
            'regulations'  => $this->importRegulations(),
            'violations'   => $this->importViolations(),
            'reward_cats'  => $this->importRewardCategories(),
            'reward_types' => $this->importRewardTypes(),
        ];
    }

    public function importRegulations(): array
    {
        $rows    = $this->readTabData(self::TAB_REGULATIONS);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (empty($row[1])) continue; // bỏ qua dòng trống

            $id          = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : null;
            $name        = trim($row[1]);
            $description = trim($row[2] ?? '');
            $effectiveDate = $this->parseDate($row[3] ?? '');
            $isActive    = $this->parseActive($row[4] ?? 'Hoạt động');

            $data = [
                'name'           => $name,
                'description'    => $description ?: null,
                'effective_date' => $effectiveDate,
                'is_active'      => $isActive,
            ];

            if ($id && Regulation::where('id', $id)->exists()) {
                Regulation::where('id', $id)->update($data);
                $updated++;
            } else {
                Regulation::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function importViolations(): array
    {
        $rows    = $this->readTabData(self::TAB_VIOLATIONS);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (empty($row[1])) continue;

            $id           = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : null;
            $name         = trim($row[1]);
            $description  = trim($row[2] ?? '');
            $severity     = $this->parseSeverity($row[3] ?? 'medium');
            $regulationId = $this->resolveParentId($row[4] ?? '', Regulation::class);
            $penaltyType  = $this->parsePenaltyType($row[5] ?? 'points');
            $pointsDed    = isset($row[6]) ? (int) $row[6] : 0;
            $moneyDed     = isset($row[7]) ? (float) $row[7] : 0;
            $isActive     = $this->parseActive($row[8] ?? 'Hoạt động');

            $data = [
                'name'           => $name,
                'description'    => $description ?: null,
                'severity'       => $severity,
                'regulation_id'  => $regulationId,
                'penalty_type'   => $penaltyType,
                'points_deducted' => $pointsDed,
                'money_deducted' => $moneyDed,
                'is_active'      => $isActive,
            ];

            if ($id && Violation::where('id', $id)->exists()) {
                Violation::where('id', $id)->update($data);
                $updated++;
            } else {
                Violation::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function importRewardCategories(): array
    {
        $rows    = $this->readTabData(self::TAB_REWARD_CATS);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (empty($row[1])) continue;

            $id          = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : null;
            $name        = trim($row[1]);
            $description = trim($row[2] ?? '');
            $isActive    = $this->parseActive($row[3] ?? 'Hoạt động');

            $data = [
                'name'        => $name,
                'description' => $description ?: null,
                'is_active'   => $isActive,
            ];

            if ($id && RewardCategory::where('id', $id)->exists()) {
                RewardCategory::where('id', $id)->update($data);
                $updated++;
            } else {
                RewardCategory::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function importRewardTypes(): array
    {
        $rows    = $this->readTabData(self::TAB_REWARD_TYPES);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (empty($row[2])) continue;

            $id          = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : null;
            $catId       = $this->resolveParentId($row[1] ?? '', RewardCategory::class);
            $name        = trim($row[2]);
            $description = trim($row[3] ?? '');
            $defPoints   = isset($row[4]) ? (int) $row[4] : 0;
            $isActive    = $this->parseActive($row[5] ?? 'Hoạt động');

            $data = [
                'reward_category_id' => $catId,
                'name'               => $name,
                'description'        => $description ?: null,
                'default_points'     => $defPoints,
                'is_active'          => $isActive,
            ];

            if ($id && RewardType::where('id', $id)->exists()) {
                RewardType::where('id', $id)->update($data);
                $updated++;
            } else {
                RewardType::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function getExistingSheetTitles(): array
    {
        $spreadsheet = $this->sheets->spreadsheets->get($this->spreadsheetId);
        return collect($spreadsheet->getSheets())
            ->map(fn($s) => $s->getProperties()->getTitle())
            ->toArray();
    }

    protected function writeTabData(string $tab, array $rows): int
    {
        // Clear old data (keep row 1 = header)
        $this->sheets->spreadsheets_values->clear(
            $this->spreadsheetId,
            "{$tab}!A2:Z",
            new ClearValuesRequest()
        );

        if (empty($rows)) return 0;

        $body = new ValueRange(['values' => $rows]);
        $this->sheets->spreadsheets_values->update(
            $this->spreadsheetId,
            "{$tab}!A2",
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );

        return count($rows);
    }

    protected function writeRow(string $tab, int $row, array $values, bool $bold = false): void
    {
        $body = new ValueRange(['values' => [$values]]);
        $this->sheets->spreadsheets_values->update(
            $this->spreadsheetId,
            "{$tab}!A{$row}",
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );
    }

    protected function readTabData(string $tab): array
    {
        $response = $this->sheets->spreadsheets_values->get(
            $this->spreadsheetId,
            "{$tab}!A2:Z"
        );
        return $response->getValues() ?? [];
    }

    protected function severityLabel(string $severity): string
    {
        return match ($severity) {
            'low'      => 'Nhẹ',
            'medium'   => 'Trung bình',
            'high'     => 'Nặng',
            'critical' => 'Nghiêm trọng',
            'extreme' => 'Đặc biệt NT',
            default    => $severity,
        };
    }

    protected function parseSeverity(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            'nhẹ', 'low'                => 'low',
            'nặng', 'high'              => 'high',
            'nghiêm trọng', 'critical'  => 'critical',
            'đặc biệt nt', 'extreme'     => 'extreme',
            default                     => 'medium',
        };
    }

    protected function penaltyTypeLabel(string $type): string
    {
        return match ($type) {
            'points' => 'Trừ điểm',
            'money'  => 'Phạt tiền',
            'both'   => 'Trừ điểm + Tiền',
            default  => $type,
        };
    }

    protected function parsePenaltyType(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            'phạt tiền', 'money'             => 'money',
            'trừ điểm + tiền', 'both'        => 'both',
            default                          => 'points',
        };
    }

    protected function parseActive(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['hoạt động', 'active', '1', 'true', 'có']);
    }

    /**
     * Parse "ID - Tên" hoặc chỉ "Tên" hoặc chỉ số ID → trả về ID hoặc null.
     * Dùng cho cột Quy Chế (Violations) và Danh Mục (RewardTypes).
     */
    protected function resolveParentId(string $value, string $modelClass): ?int
    {
        $value = trim($value);
        if ($value === '') return null;

        // Format "123 - Tên danh mục" → lấy phần số trước dấu " - "
        if (preg_match('/^(\d+)\s*-/', $value, $m)) {
            $id = (int) $m[1];
            if ($modelClass::where('id', $id)->exists()) return $id;
        }

        // Chỉ là số thuần
        if (is_numeric($value)) {
            $id = (int) $value;
            if ($modelClass::where('id', $id)->exists()) return $id;
        }

        // Tìm theo tên (trường hợp người dùng gõ tên trực tiếp)
        $record = $modelClass::where('name', $value)->first();
        return $record?->id;
    }

    protected function parseDate(string $value): ?string
    {
        if (empty($value)) return null;

        // dd/mm/yyyy
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // yyyy-mm-dd
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}
