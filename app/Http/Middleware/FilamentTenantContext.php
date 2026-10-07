<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FilamentTenantContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        app()->setLocale('ar');

        if (Auth::check()) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                return redirect()->route('filament.admin.auth.login')
                    ->withErrors(['email' => 'هذا الحساب معطل حالياً. يرجى التواصل مع الإدارة.']);
            }

            if ($user->tenant_id) {
                TenantContext::setTenantId($user->tenant_id);
            }
        }

        return $next($request);
    }
}
