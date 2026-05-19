<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class AuthService
{
    public function register(array $data): array
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return [
                'user' => $user,
                'access_token' => $token,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Register Error: ' . $e->getMessage());
            throw new Exception('Gagal mendaftarkan akun. Silakan coba lagi nanti.');
        }
    }

    public function login(array $credentials): array
    {
        try {
            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Email atau password salah.'],
                ]);
            }

            DB::beginTransaction();

            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return [
                'user' => $user,
                'access_token' => $token,
            ];
        } catch (ValidationException $e) {
            // Lepaskan exception validasi agar ditangani otomatis oleh Laravel (HTTP 422)
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Login Error: ' . $e->getMessage());
            throw new Exception('Gagal melakukan login. Silakan coba lagi nanti.');
        }
    }

    public function logout(User $user): void
    {
        try {
            $user->currentAccessToken()->delete();
        } catch (Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());
            throw new Exception('Gagal melakukan logout. Silakan coba lagi nanti.');
        }
    }
}
