<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        $user     = auth()->user();
        $employee = $user->employee?->load(['branch', 'team']);
        return view('profile.show', compact('user', 'employee'));
    }

    public function update(Request $request)
    {
        $user         = auth()->user();
        $canEditEmail = $user->can('edit-employees') || $user->hasRole(['admin', 'director']);

        $rules = [
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
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
            if (array_key_exists('position', $validated)) {
                $empUpdate['position'] = $validated['position'];
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
}
