<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TelegramStaffAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $telegramId = $request->header('X-Telegram-User-Id') ?? $request->input('telegram_user_id');

        if (! $telegramId) {
            return response()->json([
                'error' => 'Unauthorized telegram user',
                'message' => 'Telegram user ID header (X-Telegram-User-Id) or parameter is missing.',
            ], 401);
        }

        // Query user without global scopes since tenant context is not set yet
        $user = User::withoutGlobalScopes()
            ->where('telegram_user_id', $telegramId)
            ->first();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized telegram user',
                'message' => 'The provided Telegram user ID is not registered in the system.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'error' => 'Staff account is deactivated',
                'message' => 'The staff member account associated with this Telegram ID has been deactivated.',
            ], 403);
        }

        // Set the active tenant context and authenticate user for the request lifecycle
        TenantContext::setTenantId($user->tenant_id);
        Auth::setUser($user);

        return $next($request);
    }
}
