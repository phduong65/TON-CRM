<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Đăng nhập app mobile — trả về Sanctum personal access token (không dùng session/cookie).
     * Chỉ tài khoản status=active mới đăng nhập được (giống LoginController phía web).
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Auth::getProvider()->validateCredentials($user, ['password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }

        if ($user->status !== 'active') {
            $message = $user->isPending()
                ? 'Tài khoản của bạn đang chờ Admin duyệt.'
                : 'Tài khoản của bạn đã bị khoá.';

            throw ValidationException::withMessages(['email' => [$message]]);
        }

        $deviceName = $validated['device_name'] ?? substr((string) $request->userAgent(), 0, 255) ?: 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->loadMissing('employee.branch', 'employee.team');

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('employee.branch', 'employee.team');

        return response()->json(['user' => $this->formatUser($user)]);
    }

    /**
     * Đồng bộ theme từ mobile — tương đương ThemeController::toggle() (web, session-based) nhưng
     * cho phép set thẳng giá trị (thay vì chỉ toggle) vì mobile có UI chọn theme riêng.
     */
    public function updateTheme(Request $request)
    {
        $validated = $request->validate(['theme' => 'required|in:light,dark']);

        $request->user()->update(['theme' => $validated['theme']]);

        return response()->json(['ok' => true, 'theme' => $validated['theme']]);
    }

    private function formatUser(User $user): array
    {
        $employee = $user->employee;

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'theme' => $user->theme,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'employee' => $employee ? [
                'id'         => $employee->id,
                'code'       => $employee->code,
                'name'       => $employee->name,
                'position'   => $employee->position?->name,
                'branch'     => $employee->branch?->only(['id', 'name', 'code']),
                'team'       => $employee->team?->only(['id', 'name']),
                'is_active'  => $employee->is_active,
            ] : null,
        ];
    }
}
