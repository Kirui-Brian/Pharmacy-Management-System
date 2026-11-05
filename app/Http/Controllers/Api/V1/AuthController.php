<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with(['pharmacy', 'branch', 'roles', 'permissions'])
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact your pharmacy administrator.'],
            ]);
        }

        // Update last login timestamp
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        // Generate token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        // Log audit
        AuditLog::log('user_login', "User {$user->email} logged in successfully.", $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['pharmacy', 'branch', 'roles', 'permissions']);

        return response()->json([
            'status' => 'success',
            'user' => new UserResource($user),
        ]);
    }

    public function switchBranch(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $user = $request->user();

        // Only owners, super-admins, or regional managers can switch branches
        if (!$user->hasAnyRole(['owner', 'super-admin', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to switch branches.',
            ], 403);
        }

        $branch = Branch::where('id', $request->branch_id)
            ->where('pharmacy_id', $user->pharmacy_id)
            ->firstOrFail();

        $oldBranch = $user->branch_id;
        $user->branch_id = $branch->id;
        $user->save();

        AuditLog::log('branch_switched', "User {$user->email} switched active branch from #{$oldBranch} to #{$branch->id} ({$branch->name}).", $branch);

        return response()->json([
            'status' => 'success',
            'message' => "Switched active branch to {$branch->name}",
            'user' => new UserResource($user->fresh(['pharmacy', 'branch', 'roles', 'permissions'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            AuditLog::log('user_logout', "User {$user->email} logged out.", $user);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
