<?php

declare(strict_types=1);

namespace Livewire\Mechanisms;

use Livewire\LivewireManager;

if (! class_exists(ComponentRegistry::class)) {
    class ComponentRegistry
    {
        public function __construct(private readonly LivewireManager $livewireManager)
        {
        }

        public function getClass(string $name): ?string
        {
            return $this->livewireManager->getClass($name);
        }
    }
}