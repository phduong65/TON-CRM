<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('permissions', 'users')->orderBy('name')->get();
        $permissionGroups = $this->permissionGroups();
        return view('roles.index', compact('roles', 'permissionGroups'));
    }

    public function create()
    {
        $permissionGroups = $this->permissionGroups();
        return view('roles.form', compact('permissionGroups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        $role->syncPermissions($request->permissions ?? []);

        activity()->causedBy(auth()->user())
            ->log('created_role: ' . $request->name);

        return redirect()->route('roles.index')
            ->with('success', 'Tạo vai trò "' . $request->name . '" thành công.');
    }

    public function edit(Role $role)
    {
        $permissionGroups = $this->permissionGroups();
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        return view('roles.form', compact('role', 'permissionGroups', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'          => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($role->name === 'admin') {
            return back()->with('error', 'Không thể thay đổi tên vai trò admin.');
        }

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        activity()->causedBy(auth()->user())
            ->log('updated_role: ' . $request->name);

        return redirect()->route('roles.index')
            ->with('success', 'Cập nhật vai trò thành công.');
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return back()->with('error', 'Không thể xóa vai trò admin.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Không thể xóa vai trò đang có người dùng sử dụng (' . $role->users()->count() . ' người).');
        }

        activity()->causedBy(auth()->user())
            ->log('deleted_role: ' . $role->name);

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Đã xóa vai trò.');
    }
}
