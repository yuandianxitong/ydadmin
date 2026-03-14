<?php
declare(strict_types=1);

use think\migration\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            DepartmentSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            MenuSeeder::class,
            DictionarySeeder::class,
            SystemConfigSeeder::class,
            AdminSeeder::class,
        ];

        foreach ($seeders as $class) {
            /** @var Seeder $seeder */
            $seeder = new $class();
            $seeder->setAdapter($this->getAdapter());
            $seeder->run();
        }
    }
}
