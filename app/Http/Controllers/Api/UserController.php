<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserOtpMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manages the 4 dashboard/staff roles: super_admin, admin, reviewer,
 * registration_desk_officer. 'participant' is deliberately excluded from
 * every query and every assignable-role list here — participants are
 * created only through AttendeeController::store during public event
 * registration, never through this admin module.
 *
 * Every route on this controller should sit behind:
 *   ->middleware(['auth:api', 'role:super_admin'])
 * since "admin" is explicitly barred from the Add User menu entirely.
 */
class UserController extends Controller
{
    protected array $assignableRoles = [
        'super_admin',
        'admin',
        'reviewer',
        'registration_desk_officer',
    ];

    protected array $roleLabels = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'reviewer' => 'Reviewer',
        'registration_desk_officer' => 'Registration Desk Officer',
    ];

    public function index(): JsonResponse
    {
        $users = User::with('user_role')
            ->whereHas('user_role', fn ($q) => $q->whereIn('roleName', $this->assignableRoles))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (User $user) => $this->transform($user));

        $stats = [
            'total' => $users->count(),
            'active' => $users->where('status', 'active')->count(),
            'inactive' => $users->where('status', 'inactive')->count(),
            'byRole' => $users->groupBy('role')->map->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
            'stats' => $stats,
        ]);
    }

    // NOTE: keep this route registered BEFORE the '/{user}' wildcard routes
    // in api.php — the abstract-review module hit a route-ordering 404 bug
    // for exactly this reason (static path shadowed by wildcard).
    public function roles(): JsonResponse
    {
        $roles = Role::whereIn('roleName', $this->assignableRoles)->get();

        $data = $roles->map(fn ($role) => [
            'value' => $role->roleName,
            'label' => $this->roleLabels[$role->roleName] ?? $role->roleName,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'alternatePhoneNumber' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in($this->assignableRoles)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $role = Role::where('roleName', $validated['role'])->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => "Role '{$validated['role']}' hasn't been seeded yet. Run RoleSeeder first.",
            ], 422);
        }

        return DB::transaction(function () use ($validated, $role) {
            $otp = (string) random_int(100000, 999999);

            $user = User::create([
                'facilityId' => null,
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'phoneNumber' => $validated['phoneNumber'],
                'alternatePhoneNumber' => $validated['alternatePhoneNumber'] ?? null,
                // Placeholder — nobody logs in with this. Real access starts
                // with the OTP below, then changePassword() sets a real one.
                'password' => Hash::make(Str::random(40)),
                'role' => $role->roleId,
                'status' => $validated['status'] ?? 'active',
                'must_change_password' => true,
                'otp' => Hash::make($otp),
                'otp_expires_at' => now()->addHours(24),
            ]);

            Mail::to($user->email)->send(new UserOtpMail($user, $otp));

            return response()->json([
                'success' => true,
                'message' => 'User created successfully. A one-time login code was sent to their email.',
                'data' => $this->transform($user->load('user_role')),
            ], 201);
        });
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!in_array($user->user_role?->roleName, $this->assignableRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This account cannot be managed from this module.',
            ], 403);
        }

        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'alternatePhoneNumber' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->assignableRoles)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($request->user()->id === $user->id && $validated['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $role = Role::where('roleName', $validated['role'])->firstOrFail();

        $updateData = [
            'firstName' => $validated['firstName'],
            'lastName' => $validated['lastName'],
            'email' => $validated['email'],
            'phoneNumber' => $validated['phoneNumber'],
            'alternatePhoneNumber' => $validated['alternatePhoneNumber'] ?? null,
            'role' => $role->roleId,
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $this->transform($user->fresh('user_role')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if (!in_array($user->user_role?->roleName, $this->assignableRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This account cannot be managed from this module.',
            ], 403);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }

    protected function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'email' => $user->email,
            'phoneNumber' => $user->phoneNumber,
            'alternatePhoneNumber' => $user->alternatePhoneNumber,
            'role' => $user->user_role?->roleName,
            'status' => $user->status,
            'createdAt' => $user->created_at,
            'updatedAt' => $user->updated_at,
        ];
    }
}