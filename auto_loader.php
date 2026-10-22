<?php

/**
 * Minimal PSR-4-style class loader.
 *
 * Namespace prefixes are mapped explicitly because several project folders use
 * lowercase names (`app/forum/pages`) while their namespaces are capitalized
 * (`App\Forum\Pages`). A plain namespace-to-path conversion would fail on
 * case-sensitive filesystems (Linux).
 *
 * @param string $className Fully qualified class name.
 */
function loader(string $className): void
{
    static $prefixes = [
        'App\\Classes\\Install\\'   => '/app/Classes/Install/',
        'App\\Classes\\'            => '/app/Classes/',
        'App\\Forum\\Controllers\\' => '/app/forum/controllers/',
        'App\\Forum\\Pages\\'       => '/app/forum/pages/',
        'App\\Forum\\Structs\\'     => '/app/forum/structs/',
        'App\\Forum\\Widgets\\'     => '/app/forum/widgets/',
        'App\\Install\\Steps\\'     => '/app/install/Steps/',
        'App\\Install\\'            => '/app/install/',
        'App\\Interfaces\\Install\\' => '/app/interfaces/Install/',
        'App\\Interfaces\\'         => '/app/interfaces/',
        'App\\'                     => '/app/',
        'Api\\Classes\\'            => '/Api/Classes/',
        'Api\\POST\\'               => '/Api/POST/',
        'Api\\'                     => '/Api/',
    ];

    $className = ltrim($className, '\\');

    foreach ($prefixes as $prefix => $directory) {
        if (strncmp($className, $prefix, strlen($prefix)) !== 0)
            continue;

        $relative = substr($className, strlen($prefix));
        $file = __DIR__ . $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file) && is_readable($file))
            require_once $file;

        return;
    }
}

spl_autoload_register('loader');
