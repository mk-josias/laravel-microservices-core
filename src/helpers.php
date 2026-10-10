<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

if (! function_exists('microservices_declared_class')) {
    /**
     * The FQCN declared in a PHP file, read via the tokenizer (the file is NOT executed, so
     * route/config files have no side effects). Null when the file declares no named type
     * (routes, config arrays, anonymous-class migrations).
     */
    function microservices_declared_class(string $file): ?string
    {
        $tokens = token_get_all((string) @file_get_contents($file));
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = (string) ($tokens[$i + 2][1] ?? '');
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                && ($tokens[$i + 1][0] ?? null) === T_WHITESPACE
                && ($tokens[$i + 2][0] ?? null) === T_STRING) {
                return $namespace !== '' ? $namespace.'\\'.$tokens[$i + 2][1] : $tokens[$i + 2][1];
            }
        }

        return null;
    }
}

if (! function_exists('microservices_classes_with')) {
    /**
     * Discover the classes under a directory carrying a given trait/interface/attribute/parent,
     * by scanning the source tree (so classes not yet autoloaded are found, unlike
     * get_declared_classes).
     *
     * @return list<class-string>
     */
    function microservices_classes_with(string $target, string $directory): array
    {
        static $cache = [];
        $key = $target.'|'.$directory;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if (! is_dir($directory)) {
            return $cache[$key] = [];
        }

        $found = [];

        foreach (Finder::create()->files()->name('*.php')->in($directory) as $file) {
            $class = microservices_declared_class($file->getRealPath());
            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);
            $matches = $ref->isSubclassOf($target)
                || in_array($target, class_implements($class) ?: [], true)
                || in_array($target, class_uses_recursive($class), true)
                || $ref->getAttributes($target) !== [];

            if ($matches) {
                $found[] = $class;
            }
        }

        return $cache[$key] = $found;
    }
}
