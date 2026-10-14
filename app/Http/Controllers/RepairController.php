<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RepairController extends Controller
{
    public function index(): Response
    {
        $this->ensureCatalog();
        $html = file_get_contents(resource_path('repair/index.html'));
        $styles = file_get_contents(resource_path('css/repair.css'));
        $html = str_replace('</head>', '<meta name="csrf-token" content="'.e(csrf_token()).'"><style>'.$styles.'</style></head>', $html);
        $html = str_replace('<body>', '<body><div class="selfbio-header"><a class="selfbio-brand" href="/">SelfBio</a><nav aria-label="Навигация сайта"><a href="/#contacts">Контакты</a><a href="/#blog">Блог</a><a href="/puzzles">Whodoku</a><a class="active" href="/repair" aria-current="page">Ремонт</a><form method="post" action="/logout"><input type="hidden" name="_token" value="'.e(csrf_token()).'"><button type="submit">Выйти</button></form></nav></div>', $html);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function catalog(): BinaryFileResponse
    {
        $this->ensureCatalog();

        return $this->repairFile('catalog.json');
    }

    public function repairFile(?string $path = null): Response|BinaryFileResponse
    {
        if ($path === null || $path === 'index.html') {
            return $this->index();
        }

        if ($path === 'locales/ru.json') {
            return response()->file(resource_path('repair/locales/ru.json'), [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $this->privateFile(config('services.repair.root'), $path);
    }

    public function archiveFile(string $path): BinaryFileResponse
    {
        return $this->privateFile(config('services.repair.archive_root'), $path);
    }

    private function privateFile(string $root, string $path): BinaryFileResponse
    {
        $file = $this->resolveFile($root, $path);

        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'self' data:; script-src 'none'; style-src 'self' 'unsafe-inline'; object-src 'none'",
        ];

        $response = response()->file($file, $headers);
        $response->setPrivate();

        return $response;
    }

    private function resolveFile(string $root, string $path): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        abort_if(collect($segments)->contains(fn (string $part): bool => $part === '..' || str_starts_with($part, '.')), 404);

        $base = realpath($root);
        $file = $base ? realpath($base.DIRECTORY_SEPARATOR.$path) : false;
        abort_unless($base && $file && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return $file;
    }

    private function ensureCatalog(): void
    {
        $directory = config('services.repair.root');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.'/catalog.json';
        if (is_file($path)) {
            return;
        }

        $locale = json_decode(file_get_contents(resource_path('repair/locales/ru.json')), true, flags: JSON_THROW_ON_ERROR);
        $catalog = [
            'schema_version' => 1,
            'sections' => array_map(fn (string $id): array => ['id' => $id, 'messages' => []], array_keys($locale['sections'])),
        ];
        $file = @fopen($path, 'x');
        if ($file !== false) {
            fwrite($file, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
            fclose($file);
        }
    }
}
