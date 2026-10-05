<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        $user      = auth()->user();
        $employee  = $user->employee?->load(['branch', 'team', 'position']);
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        return view('profile.show', compact('user', 'employee', 'positions'));
    }

    public function update(Request $request)
    {
        $user         = auth()->user();
        $canEditEmail = $user->can('edit-employees') || $user->hasRole(['admin', 'director']);

        $rules = [
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'position_id' => 'nullable|exists:positions,id',
        ];

        if ($canEditEmail) {
            $rules['email'] = [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('employees', 'email')->ignore($user->employee?->id),
            ];
        }

        $validated = $request->validate($rules, [
            'name.required'  => 'Họ và tên là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.unique'   => 'Email này đã được sử dụng.',
        ]);

        $userUpdate = ['name' => $validated['name']];
        if ($canEditEmail && isset($validated['email'])) {
            $userUpdate['email'] = $validated['email'];
        }
        $user->update($userUpdate);

        if ($user->employee) {
            $empUpdate = [
                'name'  => $validated['name'],
                'phone' => $validated['phone'] ?? null,
            ];
            if (array_key_exists('position_id', $validated)) {
                $empUpdate['position_id'] = $validated['position_id'];
            }
            if ($canEditEmail && isset($validated['email'])) {
                $empUpdate['email'] = $validated['email'];
            }
            $user->employee->update($empUpdate);
        }

        activity()->causedBy($user)
            ->performedOn($user)
            ->inLog('profile')
            ->withProperties([
                'name'  => $user->name,
                'email' => $user->email,
            ])
            ->log('Cập nhật hồ sơ cá nhân: ' . $user->name);

        return back()->with('success', 'Hồ sơ đã được cập nhật!');
    }

    public function updatePassword(Request $request)
    {
        Validator::make($request->all(), [
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required'         => 'Mật khẩu mới là bắt buộc.',
            'password.confirmed'        => 'Xác nhận mật khẩu không khớp.',
            'password.min'              => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ])->validateWithBag('password');

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'], 'password')
                ->with('scroll_to_password', true);
        }

        $user->update(['password' => $request->password]);

        activity()->causedBy($user)
            ->performedOn($user)
            ->inLog('profile')
            ->withProperties([
                'name'  => $user->name,
                'email' => $user->email,
            ])
            ->log('Đổi mật khẩu: ' . $user->name);

        return back()->with('success', 'Mật khẩu đã được thay đổi thành công!');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'File phải là hình ảnh.',
            'avatar.mimes' => 'Ảnh đại diện chỉ chấp nhận định dạng jpeg, png, jpg, gif.',
            'avatar.max' => 'Dung lượng ảnh tối đa là 2MB.',
        ]);

        $user = auth()->user();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            
            // Ensure directory exists
            if (!file_exists(public_path('uploads/avatars'))) {
                mkdir(public_path('uploads/avatars'), 0755, true);
            }
            
            $file->move(public_path('uploads/avatars'), $filename);

            // Delete old avatar if exists
            if ($user->avatar && file_exists(public_path($user->avatar))) {
                @unlink(public_path($user->avatar));
            }

            $user->update([
                'avatar' => 'uploads/avatars/' . $filename
            ]);

            activity()->causedBy($user)
                ->performedOn($user)
                ->inLog('profile')
                ->log('Cập nhật ảnh đại diện: ' . $user->name);

            return back()->with('success', 'Ảnh đại diện đã được cập nhật thành công!');
        }

        return back()->with('error', 'Không thể tải lên ảnh đại diện.');
    }
}
