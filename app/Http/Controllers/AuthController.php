<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;


class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth
    ) {}

    public function register(Request $request)
    {
        try {
            $result = $this->auth->register($request->all());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function login(Request $request)
    {
        try {
            $result = $this->auth->login($request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
