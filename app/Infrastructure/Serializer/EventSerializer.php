<?php

namespace App\Infrastructure\Serializer;

use DateTimeImmutable;
use ReflectionClass;
use ReflectionNamedType;

class EventSerializer
{
    /**
     * Converte Objeto de Domínio -> Array JSON (Payload)
     */
    public function serialize(object $event): array
    {
        $data = [];
        $reflection = new ReflectionClass($event);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($event);

            // Se for data, formatamos para string ISO
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(DateTimeImmutable::ATOM);
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }

    /**
     * Converte DB Row (Payload + Class Name) -> Objeto de Domínio
     */
    public function deserialize(string $eventClass, array $payload): object
    {
        if (! class_exists($eventClass)) {
            throw new \RuntimeException("Event class '$eventClass' not found.");
        }

        // Reflection para inspecionar os tipos do construtor
        $reflection = new ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return new $eventClass;
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Se o payload não tem esse dado, pulamos (ou erro)
            if (! isset($payload[$name])) {
                continue;
            }

            $value = $payload[$name];

            // Auto-casting para DateTime se o construtor pedir
            if ($type instanceof ReflectionNamedType &&
                is_subclass_of($type->getName(), \DateTimeInterface::class) &&
                is_string($value)) {
                $value = new DateTimeImmutable($value);
            }

            $args[$name] = $value;
        }

        // Instancia usando Named Arguments (PHP 8 feature)
        return $reflection->newInstanceArgs($args);
    }
}
