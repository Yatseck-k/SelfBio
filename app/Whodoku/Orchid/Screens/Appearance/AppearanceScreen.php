<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Appearance;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Appearance\AppearanceService;
use Whodoku\Application\Appearance\InvalidImage;

class AppearanceScreen extends Screen
{
    public function __construct(private readonly AppearanceService $appearance) {}

    /** @return array<string, mixed> */
    public function query(): iterable
    {
        return [
            'groups' => $this->appearance->slots(),
            'seals' => $this->appearance->seals(),
            'sealDefaults' => AppearanceService::SEALS,
            'animations' => $this->appearance->animations(),
            'animationLabels' => AppearanceService::ANIMATIONS,
            'maxKb' => intdiv(AppearanceService::MAX_BYTES, 1024),
        ];
    }

    public function name(): ?string
    {
        return 'Оформление';
    }

    public function description(): ?string
    {
        return 'Изображения мебели, находок и полов, подписи печатей и анимации. Пустое поле оставляет всё как есть.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.objects'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')];
    }

    public function layout(): iterable
    {
        return [Layout::view('orchid.appearance.form')];
    }

    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => ['nullable', 'array'],
            'image.*' => ['nullable', 'file', 'max:64'],
            'restore' => ['nullable', 'array'],
            'seal' => ['nullable', 'array'],
            'seal.*' => ['nullable', 'string', 'max:24'],
            'animation' => ['nullable', 'array'],
        ]);

        $bySlot = [];
        foreach ($this->appearance->slots() as $slots) {
            foreach ($slots as $slot) {
                $bySlot[md5($slot['slot'])] = $slot['slot'];
            }
        }

        $failures = [];
        foreach ((array) $request->input('restore', []) as $hash => $on) {
            if ($on && isset($bySlot[$hash])) {
                $this->appearance->restore($bySlot[$hash], $request->user());
            }
        }
        foreach ((array) $request->file('image', []) as $hash => $file) {
            if ($file instanceof UploadedFile && isset($bySlot[$hash]) && !$request->input("restore.{$hash}")) {
                try {
                    $this->appearance->upload($bySlot[$hash], $file, $request->user());
                } catch (InvalidImage $exception) {
                    $failures[] = $bySlot[$hash].': '.$exception->getMessage();
                }
            }
        }
        $this->appearance->saveSettings((array) $request->input('seal', []), (array) $request->input('animation', []), $request->user());

        if ($failures === []) {
            Toast::success('Оформление сохранено.');
        } else {
            Toast::warning('Часть изображений не принята: '.implode('; ', $failures));
        }

        return to_route('platform.appearance');
    }
}
