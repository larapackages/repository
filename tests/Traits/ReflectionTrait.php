<?php

namespace Larapackages\Tests\Traits;

use ReflectionClass;

/**
 * Trait ReflectionTrait.
 */
trait ReflectionTrait
{
    /**
     * @param       $class
     * @param       $method
     * @param int   $parent
     * @param mixed ...$args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function getClassMethod($class, $method, int $parent = 0, ...$args)
    {
        $reflectionClass = new ReflectionClass($class);

        for ($i = 0; $i < $parent; $i++) {
            $reflectionClass = $reflectionClass->getParentClass();
        }

        $reflectionMethod = $reflectionClass->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($class, $args);
    }

    /**
     * @param     $class
     * @param     $property
     * @param int $parent
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function getClassProperty($class, $property, int $parent = 0)
    {
        $reflectionClass = new ReflectionClass($class);

        for ($i = 0; $i < $parent; $i++) {
            $reflectionClass = $reflectionClass->getParentClass();
        }

        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($class);
    }

    /**
     * @param     $class
     * @param     $property
     * @param     $value
     * @param int $parent
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function setClassProperty($class, $property, $value, int $parent = 0)
    {
        $reflectionClass = new ReflectionClass($class);

        for ($i = 0; $i < $parent; $i++) {
            $reflectionClass = $reflectionClass->getParentClass();
        }

        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($class, $value);
    }
}