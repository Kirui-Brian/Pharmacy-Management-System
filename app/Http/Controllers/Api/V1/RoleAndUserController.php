<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndUserController extends Controller
{
    /**
     * Get all users in current pharmacy.
     */
    public function getUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        $users = User::where('pharmacy_id', $user->pharmacy_id)
            ->with(['roles', 'branch'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Create a new staff user and assign role.
     */
    public function createUser(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $newUser = User::create([
            'pharmacy_id' => $currentUser->pharmacy_id,
            'branch_id' => $validated['branch_id'] ?? $currentUser->branch_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $newUser->assignRole($validated['role']);

        try {
            AuditLog::log('user_created', "User {$newUser->name} ({$newUser->email}) registered with role {$validated['role']}.");
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Staff user created successfully.',
            'data' => $newUser->load(['roles', 'branch']),
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($user->pharmacy_id !== $currentUser->pharmacy_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? $user->phone,
            'license_number' => $validated['license_number'] ?? $user->license_number,
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        try {
            AuditLog::log('user_updated', "User {$user->name} ({$user->email}) updated.");
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Staff user updated successfully.',
            'data' => $user->load(['roles', 'branch']),
        ]);
    }

    /**
     * Get all roles and their assigned permissions.
     */
    public function getRoles(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Update permissions assigned to a role.
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions']);

        try {
            AuditLog::log('role_permissions_updated', "Permissions for role {$role->name} updated (" . count($validated['permissions']) . " permissions).");
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Get all available permissions grouped by functional module.
     */
    public function getPermissions(Request $request): JsonResponse
    {
        $permissions = Permission::all();

        $grouped = [];
        foreach ($permissions as $p) {
            $parts = explode('.', $p->name);
            $module = ucfirst($parts[0] ?? 'General');

            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }

            $grouped[$module][] = [
                'id' => $p->id,
                'name' => $p->name,
                'label' => ucwords(str_replace(['.', '_'], ' ', $p->name)),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $grouped,
            'all' => $permissions,
        ]);
    }
}
