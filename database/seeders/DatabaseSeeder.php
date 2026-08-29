<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'bodega@nogal.store'],
            ['name' => 'Bodega Central', 'password' => 'password']
        );

        // Token con habilidad admin, para probar la zona de administracion.
        // En produccion esto se emite desde un endpoint de login, no desde un seeder.
        if ($admin->tokens()->count() === 0) {
            $token = $admin->createToken('seed-admin', ['admin'])->plainTextToken;
            $this->command?->info("Token admin de prueba: {$token}");
        }

        $this->call(NogalCatalogSeeder::class);
    }
}
