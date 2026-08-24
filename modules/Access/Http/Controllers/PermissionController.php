<?php

namespace Modules\Access\Http\Controllers;

use App\Core\Support\Permissions;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

/**
 * Read-only view of the permission catalogue. Permissions are defined in code
 * (App\Core\Support\Permissions) and seeded, so they are never created here.
 */
class PermissionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Permission::class);

        return Inertia::render('Access::Permissions/Index', [
            'groups' => Permissions::groups(),
            'assigned' => Permission::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
