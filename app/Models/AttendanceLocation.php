<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceLocation extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'allowed_ips',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'                => 'float',
            'longitude'               => 'float',
            'radius_meters'           => 'integer',
            'allowed_ips'             => 'array',
            'is_active'               => 'boolean',
            'ip_mismatch_alerted_at'  => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ipMismatches(): HasMany
    {
        return $this->hasMany(AttendanceIpMismatch::class);
    }

    /**
     * Số nhân viên (khác nhau) có GPS đúng nhưng IP không khớp tại điểm này, hôm nay —
     * dấu hiệu IP văn phòng có thể đã đổi. Dùng cho banner cảnh báo trên trang quản lý.
     */
    public function todayIpMismatchEmployeeCount(): int
    {
        return $this->ipMismatches()
            ->whereDate('created_at', now()->toDateString())
            ->distinct()
            ->count('employee_id');
    }

    public function latestIpMismatch(): ?AttendanceIpMismatch
    {
        return $this->ipMismatches()->latest()->first();
    }

    /**
     * Khoảng cách (mét) từ toạ độ cho trước tới điểm chấm công — công thức Haversine.
     */
    public function distanceMeters(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // mét

        $latFrom = deg2rad((float) $this->latitude);
        $lngFrom = deg2rad((float) $this->longitude);
        $latTo   = deg2rad($lat);
        $lngTo   = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function isWithinRadius(float $lat, float $lng): bool
    {
        return $this->distanceMeters($lat, $lng) <= $this->radius_meters;
    }

    /**
     * So khớp IP với danh sách allowed_ips — hỗ trợ IP đơn hoặc CIDR (VD "203.0.113.0/24").
     */
    public function matchesIp(string $ip): bool
    {
        foreach (($this->allowed_ips ?? []) as $entry) {
            if ($this->ipMatchesEntry($ip, trim((string) $entry))) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesEntry(string $ip, string $entry): bool
    {
        if ($entry === '') {
            return false;
        }

        if (!str_contains($entry, '/')) {
            return $ip === $entry;
        }

        [$subnet, $maskBits] = explode('/', $entry, 2);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $maskBits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
