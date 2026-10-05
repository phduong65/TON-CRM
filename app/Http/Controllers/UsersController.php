<?php

namespace App\Http\Controllers;

use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        // 'permissions' = quyền riêng (direct) — cột "Quyền riêng" + modal Sửa, tránh N+1
        // 'employee' = hồ sơ nhân viên liên kết — cột "TT Nhân viên", tránh N+1
        $query = User::with(['roles', 'permissions', 'employee'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhereHas('employee', fn($eq) => $eq->where('code', 'like', "%$s%")));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $empStatus = $request->input('employee_status', 'active');
        if ($empStatus === 'active' || $empStatus === '1') {
            $query->whereHas('employee', fn($q) => $q->where('is_active', true));
        } elseif ($empStatus === 'resigned' || $empStatus === '0') {
            $query->whereHas('employee', fn($q) => $q->where('is_active', false));
        } elseif ($empStatus === 'unlinked' || $empStatus === 'none') {
            $query->doesntHave('employee');
        } elseif ($empStatus === 'all') {
            // Không lọc theo trạng thái nhân viên
        }

        // Base query tính số lượng theo tab trạng thái nhân viên (áp dụng search + role)
        $tabBase = User::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $tabBase->where(fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhereHas('employee', fn($eq) => $eq->where('code', 'like', "%$s%")));
        }
        if ($request->filled('role')) {
            $tabBase->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        $statusCounts = [
            'all'      => (clone $tabBase)->count(),
            'active'   => (clone $tabBase)->whereHas('employee', fn($q) => $q->where('is_active', true))->count(),
            'resigned' => (clone $tabBase)->whereHas('employee', fn($q) => $q->where('is_active', false))->count(),
            'unlinked' => (clone $tabBase)->doesntHave('employee')->count(),
        ];

        // Base query tính số lượng theo vai trò (áp dụng search + employee_status)
        $roleBase = User::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $roleBase->where(fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhereHas('employee', fn($eq) => $eq->where('code', 'like', "%$s%")));
        }
        if ($empStatus === 'active' || $empStatus === '1') {
            $roleBase->whereHas('employee', fn($q) => $q->where('is_active', true));
        } elseif ($empStatus === 'resigned' || $empStatus === '0') {
            $roleBase->whereHas('employee', fn($q) => $q->where('is_active', false));
        } elseif ($empStatus === 'unlinked' || $empStatus === 'none') {
            $roleBase->doesntHave('employee');
        }

        $totalForRoles = (clone $roleBase)->count();
        $roleCounts = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('model_id', (clone $roleBase)->select('id'))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('roles.name')
            ->pluck(DB::raw('count(*)'), 'roles.name')
            ->all();

        $users = $query->paginate(15)->withQueryString();
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissionGroups = $this->permissionGroups();

        return view('users.index', compact(
            'users', 'roles', 'permissionGroups', 'statusCounts', 'roleCounts', 'totalForRoles'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'role'                  => 'required|exists:roles,name',
            'permissions'           => 'nullable|array',
            'permissions.*'         => 'string|exists:permissions,name',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);

        $user->assignRole($request->role);

        if ($request->filled('permissions')) {
            $user->syncPermissions($request->permissions);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties([
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $request->role,
            ])
            ->log('Tạo người dùng ' . $user->name . ' — Vai trò: ' . $request->role);

        return redirect()->route('users.index')
            ->with('success', 'Tạo người dùng "' . $user->name . '" thành công.');
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'role'          => 'required|exists:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        $request->validate($rules);

        // Tài khoản tự đăng ký ở trạng thái 'pending' (xem RegisterController) chưa có vai trò gì
        // — hành động Sửa (gán vai trò + lưu) của Admin ở đây ĐỒNG THỜI là hành động Duyệt: tự
        // động chuyển sang 'active' để tài khoản có thể đăng nhập.
        $wasPending = $user->isPending();

        $updateData = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if ($wasPending) {
            $updateData['status'] = 'active';
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);
        $user->syncRoles([$request->role]);
        // Chỉ đồng bộ quyền riêng khi form thực sự có mục "Quyền riêng" (cờ sync_permissions).
        // Request thiếu cờ (form/client cũ không gửi permissions[]) không được xoá sạch quyền riêng.
        if ($request->boolean('sync_permissions')) {
            $user->syncPermissions($request->permissions ?? []);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties([
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $request->role,
                'password_changed' => $request->filled('password') ? 'Có' : 'Không',
                'approved'         => $wasPending ? 'Có' : 'Không',
            ])
            ->log(($wasPending ? 'Duyệt' : 'Cập nhật') . ' người dùng ' . $user->name . ' — Vai trò: ' . $request->role);

        if ($wasPending) {
            app(NotificationService::class)->notifyUserApproved($user);

            try {
                Mail::to($user->email)->send(new AccountApprovedMail($user));
            } catch (\Throwable $e) {
                Log::error('Failed to send account-approved email: ' . $e->getMessage());
            }

            return redirect()->route('users.index')
                ->with('success', 'Đã duyệt và kích hoạt tài khoản "' . $user->name . '".');
        }

        return redirect()->route('users.index')
            ->with('success', 'Cập nhật người dùng "' . $user->name . '" thành công.');
    }

    /**
     * Từ chối tài khoản tự đăng ký đang chờ duyệt — chuyển sang 'inactive' (không gán vai trò,
     * không thể đăng nhập). Khác với toggleStatus() (chỉ dành cho tài khoản đã active/inactive).
     */
    public function reject(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể thay đổi trạng thái tài khoản đang đăng nhập.');
        }

        if (!$user->isPending()) {
            return back()->with('error', 'Chỉ có thể từ chối tài khoản đang chờ duyệt.');
        }

        $user->update(['status' => 'inactive']);

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties(['name' => $user->name, 'email' => $user->email])
            ->log('Từ chối đăng ký tài khoản ' . $user->name . ' (' . $user->email . ')');

        app(NotificationService::class)->notifyUserRejected($user);

        try {
            Mail::to($user->email)->send(new AccountRejectedMail($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send account-rejected email: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã từ chối đăng ký của "' . $user->name . '".');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể thay đổi trạng thái tài khoản đang đăng nhập.');
        }

        if ($user->isPending()) {
            return back()->with('error', 'Tài khoản đang chờ duyệt — dùng "Duyệt" để gán vai trò và kích hoạt, hoặc "Từ chối" để từ chối đăng ký.');
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        $statusLabel = $newStatus === 'inactive' ? 'Tạm khóa' : 'Kích hoạt';
        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties([
                'name'       => $user->name,
                'email'      => $user->email,
                'new_status' => $newStatus === 'inactive' ? 'Tạm khóa' : 'Hoạt động',
            ])
            ->log($statusLabel . ' tài khoản ' . $user->name . ' (' . $user->email . ')');

        $label = $newStatus === 'inactive' ? 'tạm khóa' : 'kích hoạt';
        return back()->with('success', "Đã $label tài khoản \"{$user->name}\".");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể xóa tài khoản đang đăng nhập.');
        }

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties([
                'name'  => $user->name,
                'email' => $user->email,
            ])
            ->log('Xóa người dùng ' . $user->name . ' (' . $user->email . ')');

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Đã xóa người dùng.');
    }
}
