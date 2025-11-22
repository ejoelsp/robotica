<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule; 
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            // Siempre fuerza JSON (evita HTML en errores)
            if (! $request->expectsJson()) {
                $request->headers->set('Accept', 'application/json');
            }

            $validated = $request->validate([
                'name'     => ['required','string','max:150'],
                'email'    => ['required','email', Rule::unique(User::class, 'email')],
                'password' => ['required','string','min:6'],
            ]);

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id'  => 3, // competidor
            ]);

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'user'    => $user,
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('REGISTER_FAILED', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error'   => 'REGISTER_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }

    public function refresh()
    {
        try {
            // Toma el token del header Authorization y lo refresca (permite expirado si está dentro de refresh_ttl)
            $newToken = JWTAuth::parseToken()->refresh();

            return response()->json([
                'access_token' => $newToken,
                'token_type'   => 'bearer',
                'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            ]);
        } catch (TokenBlacklistedException $e) {
            return response()->json(['error' => 'TOKEN_BLACKLISTED'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'TOKEN_INVALID'], 401);
        } catch (JWTException $e) {
            // No llegó token o no se pudo parsear
            return response()->json(['error' => 'TOKEN_ABSENT_OR_JWT_ERROR', 'msg' => $e->getMessage()], 401);
        }
    }


}