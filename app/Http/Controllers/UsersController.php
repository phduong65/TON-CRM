<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])->orderBy('name')->paginate(15);
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
        ]);

        $user->assignRole($request->role);

        if ($request->filled('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log('created_user');

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

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log('updated_user');

        return redirect()->route('users.index')
            ->with('success', 'Cập nhật người dùng "' . $user->name . '" thành công.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể xóa tài khoản đang đăng nhập.');
        }

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log('deleted_user');

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Đã xóa người dùng.');
    }
}
