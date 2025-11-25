<?php

// namespace App\Http\Controllers;

// use App\Models\User;
// use Illuminate\Http\Request;
// use Illuminate\Validation\ValidationException;
// use Illuminate\Support\Facades\Hash;

// class AuthController extends Controller
// {
//     public function register(Request $request){
//         $data = $request->validate([
//             'name'     => 'required|string|max:150',
//             'email'    => 'required|email|max:150|unique:seguridad.users,email',
//             'password' => 'required|string|min:6',
//             'role_id'  => 'required|integer|exists:seguridad.roles,id',
//         ]);

//         $user = User::create([
//             'name'     => $data['name'],
//             'email'    => $data['email'],
//             'password' => Hash::make($data['password']), 
//             'role_id'  => $data['role_id'],
//         ]);

//         return response()->json(['user'=>$user], 201);
//     }

//     public function login(Request $request){
//         $credentials = $request->validate([
//             'email'    => 'required|email',
//             'password' => 'required|string',
//         ]);

//         if (!$token = auth('api')->attempt($credentials)) {
//             throw ValidationException::withMessages([
//                 'email' => ['Credenciales inválidas.'],
//             ]);
//         }

//         return response()->json([
//             'access_token' => $token,
//             'token_type'   => 'bearer',
//             'expires_in'   => auth('api')->factory()->getTTL() * 60,
//             'user'         => auth('api')->user(),
//         ]);
//     }

//     public function me(){
//         return response()->json(auth('api')->user());
//     }

//     public function logout(){
//         auth('api')->logout();
//         return response()->json(['message'=>'Sesión cerrada']);
//     }

//     public function refresh(){
//         return response()->json([
//             'access_token' => auth('api')->refresh(),
//             'token_type'   => 'bearer',
//             'expires_in'   => auth('api')->factory()->getTTL() * 60,
//         ]);
//     }
// }
