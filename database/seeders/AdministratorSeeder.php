<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('name', 'Test User')
            ->where('email', 'test@example.com')
            ->first()
            ?->delete();

        $name = config('sirca.administrator.name');
        $email = config('sirca.administrator.email');
        $password = config('sirca.administrator.password');

        if (blank($name) || blank($email) || blank($password)) {
            throw new RuntimeException('Configure SIRCA_ADMIN_NAME, SIRCA_ADMIN_EMAIL, and SIRCA_ADMIN_PASSWORD before seeding the administrator.');
        }

        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $administrator = User::withTrashed()->firstOrNew(['email' => $email]);

        if ($administrator->exists && $administrator->trashed()) {
            $administrator->restore();
        }

        $administrator->name = $name;
        $administrator->role()->associate($role);
        $administrator->department()->dissociate();
        $administrator->email_verified_at = now();
        $administrator->password = $password;
        $administrator->save();
    }
}
