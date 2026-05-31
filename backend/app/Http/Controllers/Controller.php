<?php

// app/Http/Controllers/Controller.php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Check if user has permission
     */
    protected function checkPermission($permission)
    {
        if (! auth()->user()->can($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
