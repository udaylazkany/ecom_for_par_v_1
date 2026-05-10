<?php
namespace App\Services;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
class AuthService
{
    public function __construct(
        private UserRepositoryInterface $users
    ) {}

    public function register(array $data)
    {
        // Validation بسيط
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            throw new \Exception('Missing required fields');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        if (strlen($data['password']) < 6) {
            throw new \Exception('Password too short');
        }

        // Hash
        $data['password'] = bcrypt($data['password']);

        // Create user
        $user = $this->users->create($data);

        return [
            'user' => $user,
            'token' => $user->createToken('auth')->plainTextToken
        ];
    }

    public function login(array $data)
    {
        $user = $this->users->findByEmail($data['email']);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth')->plainTextToken
        ];
    }
}
