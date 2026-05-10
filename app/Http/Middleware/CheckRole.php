<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next,...$roles): Response
    {
        $user=$request->user();
        if(!$user)
            {
                return response()->json(['message'=>'غير مصرح'],401);
            }
        if($user->hasAnyRole($roles))
            {
                return $next($request);
            }
        return response()->json(['message'=>'ليس لديك صلاحية '],403);
    }
}
