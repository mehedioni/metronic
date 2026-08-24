<?php

namespace App\Core;

use App\Core\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

/**
 * Base controller for JSON API endpoints (app/Http/Controllers/Api and
 * modules/<Module>/Http/Controllers/Api). Inertia page controllers should
 * extend App\Http\Controllers\Controller instead.
 */
abstract class BaseApiController extends Controller
{
    use ApiResponse, AuthorizesRequests;
}
