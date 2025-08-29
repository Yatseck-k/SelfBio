<?php return  [
  'inertiajs/inertia-laravel' => 
   [
    'providers' => 
     [
      0 => \Inertia\ServiceProvider::class,
    ],
  ],
  'laravel/horizon' => 
   [
    'aliases' => 
     [
      'Horizon' => \Laravel\Horizon\Horizon::class,
    ],
    'providers' => 
     [
      0 => \Laravel\Horizon\HorizonServiceProvider::class,
    ],
  ],
  'laravel/pail' => 
   [
    'providers' => 
     [
      0 => \Laravel\Pail\PailServiceProvider::class,
    ],
  ],
  'laravel/sail' => 
   [
    'providers' => 
     [
      0 => \Laravel\Sail\SailServiceProvider::class,
    ],
  ],
  'laravel/sanctum' => 
   [
    'providers' => 
     [
      0 => \Laravel\Sanctum\SanctumServiceProvider::class,
    ],
  ],
  'laravel/tinker' => 
   [
    'providers' => 
     [
      0 => \Laravel\Tinker\TinkerServiceProvider::class,
    ],
  ],
  'livewire/flux' => 
   [
    'aliases' => 
     [
      'Flux' => \Flux\Flux::class,
    ],
    'providers' => 
     [
      0 => \Flux\FluxServiceProvider::class,
    ],
  ],
  'livewire/livewire' => 
   [
    'aliases' => 
     [
      'Livewire' => \Livewire\Livewire::class,
    ],
    'providers' => 
     [
      0 => \Livewire\LivewireServiceProvider::class,
    ],
  ],
  'moonshine/moonshine' => 
   [
    'providers' => 
     [
      0 => \MoonShine\Laravel\Providers\MoonShineServiceProvider::class,
    ],
  ],
  'nesbot/carbon' => 
   [
    'providers' => 
     [
      0 => \Carbon\Laravel\ServiceProvider::class,
    ],
  ],
  'nunomaduro/collision' => 
   [
    'providers' => 
     [
      0 => \NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class,
    ],
  ],
  'nunomaduro/termwind' => 
   [
    'providers' => 
     [
      0 => \Termwind\Laravel\TermwindServiceProvider::class,
    ],
  ],
  'tightenco/ziggy' => 
   [
    'providers' => 
     [
      0 => \Tighten\Ziggy\ZiggyServiceProvider::class,
    ],
  ],
  'vladimir-yuldashev/laravel-queue-rabbitmq' => 
   [
    'providers' => 
     [
      0 => \VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider::class,
    ],
  ],
];