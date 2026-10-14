@php
    $fmt = fn (array $values) => implode(' · ', array_map(fn ($v) => number_format($v, 2, ',', ''), $values));
@endphp

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5>Как проверять</h5>
    <ol class="mb-2">
        <li>Откройте дело на сайте (нужен вход игроком; гостю доступны только гостевые дела), решите его.</li>
        <li>Поставьте звёзды: 1 — очень просто … 5 — очень трудно. Оценку системы мы показываем только после вашей, чтобы проверка была слепой.</li>
        <li>Когда оцените дела разных уровней, ниже появятся предложенные пороги.</li>
    </ol>
    <p class="text-muted mb-0">Пороги уровней 2–5 сейчас: <strong>{{ $fmt($thresholds) }}</strong>{{ $custom ? ' (изменены, по умолчанию: '.$fmt($defaults).')' : ' (по умолчанию)' }}.</p>
</div>

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5>Дела для оценки</h5>
    @if (count($rows) === 0)
        <p class="text-muted mb-0">Нет опубликованных дел с измеренной сложностью. Выпустите дела на экране «Выпуск дел» и выполните <code>php artisan puzzles:rate --all</code>.</p>
    @else
        <table class="table table-sm align-middle">
            <thead><tr><th>Дело</th><th>Поле</th><th>Ваша оценка</th><th>Система</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><a href="{{ url('/puzzles/'.$row['slug']) }}" target="_blank" rel="noopener">{{ $row['title'] }}</a></td>
                        <td>{{ $row['size'] }}</td>
                        <td>
                            @foreach (range(1, 5) as $stars)
                                <label class="me-2">
                                    <input type="radio" name="stars[{{ $row['id'] }}]" value="{{ $stars }}" @checked($row['mine'] === $stars)> {{ $stars }}★
                                </label>
                            @endforeach
                        </td>
                        <td>@if ($row['system'] !== null){{ str_repeat('★', $row['system']) }} <span class="text-muted">(балл {{ number_format($row['score'], 2, ',', '') }})</span>@else<span class="text-muted">после вашей оценки</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($agreement[1] > 0)
            <p class="mb-0">Совпало с системой: <strong>{{ $agreement[0] }} из {{ $agreement[1] }}</strong>.</p>
        @endif
    @endif
</div>

@if ($suggestion)
    <div class="bg-white rounded shadow-sm p-4 mb-3">
        <h5>Предложенные пороги</h5>
        <p>По {{ $suggestion['votes'] }} оценкам: <strong>{{ $fmt($suggestion['thresholds']) }}</strong> (сейчас: {{ $fmt($thresholds) }}).</p>
        <table class="table table-sm w-auto">
            <thead><tr><th>Ваша оценка</th><th class="text-end">Дел</th><th class="text-end">Средний балл системы</th></tr></thead>
            <tbody>
                @foreach ($suggestion['counts'] as $stars => $count)
                    <tr><td>{{ str_repeat('★', $stars) }}</td><td class="text-end">{{ $count }}</td><td class="text-end">{{ number_format($suggestion['means'][$stars], 2, ',', '') }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="text-muted mb-0">Порог между k и k+1 звёздами — середина средних баллов этих групп; уровень без оценок сохраняет прежний порог. Чем больше дел вы оцените, тем точнее.</p>
    </div>
@endif
