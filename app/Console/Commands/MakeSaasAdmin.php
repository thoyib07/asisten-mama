<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Admin SaaS tidak punya halaman registrasi — satu-satunya cara membuatnya adalah
 * lewat command ini. Opsi boleh dilewat lengkap supaya bisa dipakai saat deploy.
 */
class MakeSaasAdmin extends Command
{
    protected $signature = 'make:saas-admin
        {--name= : Nama admin}
        {--email= : Email admin}
        {--password= : Password admin}
        {--role=admin : Role admin (owner|admin)}';

    protected $description = 'Buat akun admin SaaS baru (panel /admin)';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Nama', required: true),
            'email' => $this->option('email') ?: text('Email', required: true),
            'password' => $this->option('password') ?: password('Password', required: true),
            'role' => $this->option('role'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:admins,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(Admin::roleOptions()))],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create($data);

        $this->info("Admin dibuat: {$admin->email} (role: {$admin->role})");

        return self::SUCCESS;
    }
}
