<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateWebhookRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Helper para tipar corretamente o Guard JWT
     *
     * @return \PHPOpenSourceSaver\JWTAuth\JWTGuard
     */
    private function guard()
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard */
        return Auth::guard('api');
    }

    /**
     * Helper para formatar resposta do token
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return $this->success([
            'token' => $token,
            'type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            /** @var User $user */
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'version' => 1,
            ]);

            $token = $this->guard()->login($user);

            return $this->success([
                'user' => $user,
                'token' => $token,
                'type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60,
            ], 'User created successfully', 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! $token = $this->guard()->attempt($credentials)) {
            return $this->error('Unauthorized', 401);
        }

        return $this->respondWithToken($token);
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return $this->success(null, 'Successfully logged out');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->guard()->user();

        return $this->success($user->load('wallet'));
    }

    /**
     * Atualiza a URL de Webhook do usuário autenticado
     */
    public function updateWebhook(UpdateWebhookRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->guard()->user();

        $user->update([
            'webhook_url' => $request->url,
        ]);

        return $this->success(
            ['webhook_url' => $user->webhook_url],
            'Webhook configuration updated successfully'
        );
    }
}
