<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Access\Http\Requests\ListUserRequest;
use Modules\Access\Http\Requests\StoreRoleRequest;
use Modules\Access\Http\Requests\UpdateRoleRequest;
use Modules\Access\Services\RoleService;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(ListUserRequest $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Access::Roles/Index', [
            'roles' => $this->roles->paginate($request->filters()),
            'filters' => $request->filters(),
            'permissionGroups' => $this->roles->catalogue(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $this->roles->create($request->validated(), $request->user());

        return back()->with('success', 'Role created.');
    }

    public function show(Role $role): Response
    {
        $this->authorize('view', $role);

        return Inertia::render('Access::Roles/Show', [
            'role' => $role->load('permissions:id,name'),
            'permissionGroups' => $this->roles->catalogue(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roles->update($role, $request->validated(), $request->user());

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $this->roles->delete($role);

        return redirect()->route('access.roles.index')->with('success', 'Role deleted.');
    }
}
