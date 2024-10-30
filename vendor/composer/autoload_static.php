<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit55e6680ca736746f3dfb18b6018515df
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LJPc\\EasyLogin\\' => 15,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LJPc\\EasyLogin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit55e6680ca736746f3dfb18b6018515df::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit55e6680ca736746f3dfb18b6018515df::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit55e6680ca736746f3dfb18b6018515df::$classMap;

        }, null, ClassLoader::class);
    }
}
