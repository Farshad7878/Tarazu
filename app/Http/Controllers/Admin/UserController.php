<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // فیلتر اختیاری بر اساس نقش (مثلاً role=reception برای لیست ریسپشن‌ها)
        $users = User::query()
            ->with('branch:id,name')
            ->when($request->role, fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'branch_id' => $user->branch_id,
                    'branch_name' => $user->branch?->name,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at->format('Y-m-d H:i'),
                ];
            })
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,reception',
            'branch_id' => 'required_if:role,reception|nullable|integer|exists:branches,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'branch_id' => $request->role === 'reception' ? $request->branch_id : null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'کاربر با موفقیت ایجاد شد.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
            ]
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,reception',
            'branch_id' => 'required_if:role,reception|nullable|integer|exists:branches,id',
            'is_active' => 'required|boolean',
        ]);

        // جلوگیری از قفل شدن ادمین بیرون از سیستم
        if ($user->id === auth()->id() && (! $request->boolean('is_active') || $request->role !== 'admin')) {
            return response()->json([
                'error' => 'نمی‌توانید نقش یا وضعیت فعال بودن حساب خودتان را تغییر دهید.',
            ], 422);
        }

        $user->update(array_merge(
            $request->only('name', 'email', 'role', 'is_active'),
            ['branch_id' => $request->role === 'reception' ? $request->branch_id : null],
        ));

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json(['message' => 'کاربر با موفقیت بروزرسانی شد.']);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'نمی‌توانید حساب خودتان را حذف کنید.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'کاربر با موفقیت حذف شد.']);
    }
}