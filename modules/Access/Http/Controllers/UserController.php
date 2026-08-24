<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Access\Http\Requests\ListUserRequest;
use Modules\Access\Http\Requests\StoreUserRequest;
use Modules\Access\Http\Requests\UpdateUserRequest;
use Modules\Access\Services\UserService;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(ListUserRequest $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Access::Users/Index', [
            'users' => $this->users->paginate($request->filters()),
            'filters' => $request->filters(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->users->create($request->validated(), $request->user());

        return back()->with('success', 'User created.');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Access::Users/Show', [
            'user' => $user->load('roles:id,name'),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update($user, $request->validated(), $request->user());

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return redirect()->route('access.users.index')->with('success', 'User deleted.');
    }

    /**
     * Activate or deactivate an account. A deactivated user is signed out on
     * their next request by App\Http\Middleware\EnsureUserIsActive.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $this->users->setActive($user, ! $user->is_active);

        return back()->with('success', 'User status updated.');
    }
}
