<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['roles'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate(15)->withQueryString();
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissionGroups = $this->permissionGroups();

        return view('users.index', compact('users', 'roles', 'permissionGroups'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $permissionGroups = $this->permissionGroups();
        return view('users.form', compact('roles', 'permissionGroups'));
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

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $permissionGroups = $this->permissionGroups();
        $userRole = $user->roles->first()?->name ?? '';
        $userDirectPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $rolePermissions = $user->getPermissionsViaRoles()->pluck('name')->toArray();

        return view('users.form', compact(
            'user', 'roles', 'permissionGroups',
            'userRole', 'userDirectPermissions', 'rolePermissions'
        ));
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

        $updateData = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);
        $user->syncRoles([$request->role]);
        $user->syncPermissions($request->permissions ?? []);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->inLog('user')
            ->withProperties([
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $request->role,
                'password_changed' => $request->filled('password') ? 'Có' : 'Không',
            ])
            ->log('Cập nhật người dùng ' . $user->name . ' — Vai trò: ' . $request->role);

        return redirect()->route('users.index')
            ->with('success', 'Cập nhật người dùng "' . $user->name . '" thành công.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể thay đổi trạng thái tài khoản đang đăng nhập.');
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
