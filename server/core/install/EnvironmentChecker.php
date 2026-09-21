<?php

declare(strict_types=1);

namespace core\install;

final class EnvironmentChecker
{
    private const EXTENSIONS = [
        'pdo_mysql',
        'redis',
        'pcntl',
        'posix',
        'mbstring',
        'json',
        'openssl',
        'curl',
    ];

    /**
     * @return list<array{key:string,name:string,required:string,current:string,ok:bool,critical:bool}>
     */
    public function check(): array
    {
        $items = [
            $this->item(
                'php',
                lang('install.env_php'),
                '>= 8.4',
                PHP_VERSION,
                version_compare(PHP_VERSION, '8.4.0', '>='),
            ),
        ];

        foreach (self::EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $items[] = $this->item(
                $extension,
                lang('install.env_extension', ['name' => $extension]),
                'installed',
                $loaded ? 'installed' : 'missing',
                $loaded,
            );
        }

        $runtime = runtime_path();
        $runtimeOk = is_dir($runtime) && is_writable($runtime);
        $items[] = $this->item(
            'runtime',
            lang('install.env_writable', ['path' => 'runtime']),
            'writable',
            $runtimeOk ? 'writable' : 'not writable',
            $runtimeOk,
        );

        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';
        $envOk = is_file($envPath) ? is_writable($envPath) : is_writable(base_path());
        $items[] = $this->item(
            'env',
            lang('install.env_writable', ['path' => '.env']),
            'writable',
            $envOk ? 'writable' : 'not writable',
            $envOk,
        );

        return $items;
    }

    /**
     * @return array{key:string,name:string,required:string,current:string,ok:bool,critical:bool}
     */
    private function item(string $key, string $name, string $required, string $current, bool $ok): array
    {
        return [
            'key'      => $key,
            'name'     => $name,
            'required' => $required,
            'current'  => $current,
            'ok'       => $ok,
            'critical' => true,
        ];
    }
}
