<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'status',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'theme' => 'string',
            'status' => 'string',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Nhân viên đã từng được xếp ít nhất 1 ca làm việc (ShiftSchedule) — dùng để
     * ẩn các mục chấm công cá nhân (Chấm công / Lịch sử chấm công) khỏi những
     * tài khoản chưa có ca nên chưa thể tự chấm công.
     */
    public function hasAssignedShift(): bool
    {
        return $this->employee?->shiftSchedules()->exists() ?? false;
    }

    /**
     * Admin không tự chấm công cá nhân; nhân viên chưa được xếp ca cũng chưa
     * cần thấy các mục này. Dùng để ẩn "Chấm công" / "Lịch sử chấm công" khỏi
     * sidebar, topbar và dashboard.
     */
    public function canSeeSelfAttendance(): bool
    {
        return !$this->hasRole('admin') && $this->hasAssignedShift();
    }

    /**
     * Tài khoản tự đăng ký (routes/auth.php — register) đang chờ Admin duyệt (gán vai trò +
     * kích hoạt) trước khi được phép đăng nhập. Xem LoginController, UsersController::update().
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
