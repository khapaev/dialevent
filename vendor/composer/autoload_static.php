<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite18b4121b6f06ecd8bf4b220cbb40052
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'PAMI\\' => 5,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'PAMI\\' => 
        array (
            0 => __DIR__ . '/..' . '/marcelog/pami/src/PAMI',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Globals' => __DIR__ . '/../..' . '/src/Globals.php',
        'Helper' => __DIR__ . '/../..' . '/src/Helper.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite18b4121b6f06ecd8bf4b220cbb40052::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite18b4121b6f06ecd8bf4b220cbb40052::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite18b4121b6f06ecd8bf4b220cbb40052::$classMap;

        }, null, ClassLoader::class);
    }
}
