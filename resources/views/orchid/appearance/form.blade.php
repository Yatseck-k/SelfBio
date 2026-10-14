@php
    $titles = ['kind' => 'Мебель и предметы', 'finding' => 'Находки на месте преступления', 'seal' => 'Печати и штампы (картинка необязательна)', 'floor' => 'Полы комнат (бесшовный узор)'];
@endphp
<p class="text-muted">
    Загрузите SVG (лучше), PNG или WebP до {{ $maxKb }} КБ. SVG очищается от скриптов и внешних ссылок и должен иметь <code>viewBox</code>.
    Изображение показывается на карте, в легенде и в каталоге вместо стандартного. «Вернуть стандартное» удаляет вашу загрузку.
</p>

@foreach ($groups as $group => $slots)
    @continue(empty($slots))
    <div class="bg-white rounded shadow-sm p-4 mb-3">
        <h4 class="mb-3">{{ $titles[$group] }}</h4>
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th style="width:5rem">Сейчас</th><th>Место</th><th>Новое изображение</th><th style="width:12rem">Вернуть</th></tr></thead>
            <tbody>
            @foreach ($slots as $slot)
                @php $hash = md5($slot['slot']); @endphp
                <tr>
                    <td>
                        @if ($slot['url'])
                            <span style="display:inline-block;width:3.5rem;height:3.5rem;background:#f2e4ce;border:1px solid #d7c7a6;border-radius:6px;{{ $group === 'floor' ? 'background-image:url('.$slot['url'].');background-size:1.5rem;' : '' }}">
                                @if ($group !== 'floor')<img src="{{ $slot['url'] }}" alt="" style="width:100%;height:100%;object-fit:contain">@endif
                            </span>
                        @else
                            <span class="text-muted small">нет</span>
                        @endif
                    </td>
                    <td>{{ $slot['label'] }} <span class="text-muted small d-block">{{ $slot['custom'] ? 'своё изображение' : ($slot['default'] ? 'стандартное' : 'без картинки') }}</span></td>
                    <td><input type="file" name="image[{{ $hash }}]" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp" class="form-control form-control-sm"></td>
                    <td>@if ($slot['custom'])<label class="form-check"><input type="checkbox" class="form-check-input" name="restore[{{ $hash }}]" value="1"> <span class="form-check-label">стандартное</span></label>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h4 class="mb-3">Подписи печатей</h4>
    <div class="row g-3">
        @foreach ($sealDefaults as $code => $default)
            <div class="col-md-3">
                <label class="form-label" for="seal-{{ $code }}">{{ $default }}</label>
                <input id="seal-{{ $code }}" class="form-control" name="seal[{{ $code }}]" maxlength="24" value="{{ $seals[$code] }}">
            </div>
        @endforeach
    </div>
</div>

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h4 class="mb-2">Анимации</h4>
    <p class="text-muted small">У игроков, которые отключили движение в системе, анимации не работают в любом случае.</p>
    <label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="animation[enabled]" value="1" @checked($animations['enabled'])> <span class="form-check-label"><strong>Анимации включены</strong></span></label>
    @foreach ($animationLabels as $code => $label)
        <label class="form-check d-block"><input type="checkbox" class="form-check-input" name="animation[{{ $code }}]" value="1" @checked($animations[$code])> <span class="form-check-label">{{ $label }}</span></label>
    @endforeach
</div>
