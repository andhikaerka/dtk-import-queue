<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase; 

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
    }

    public function test_can_register_user_successfully()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->register($data);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('John Doe', $result['user']->name);
        
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
        ]);
    }

    public function test_can_login_user_successfully()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'login@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->login($credentials);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'login@example.com',
            'password' => 'wrongpassword',
        ];

        $this->expectException(ValidationException::class);

        $this->authService->login($credentials);
    }

    public function test_can_logout_user_successfully()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');
        
        // Assert token was created
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
        
        // Simulasikan user yang sedang login menggunakan token tersebut
        $user->withAccessToken($token->accessToken);

        $this->authService->logout($user);

        // Assert token telah dihapus
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
