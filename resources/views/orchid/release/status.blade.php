@php
    $labels = ['starter' => 'Стартовый набор', 'reserve' => 'Резерв', 'daily' => 'Выпуск сегодняшних дел', 'story' => 'Дела сюжета'];
    $states = ['running' => 'идёт', 'done' => 'готово', 'failed' => 'ошибка'];
@endphp

@if ($current)
    <div class="alert alert-info">
        <strong>Идёт: {{ $labels[$current->kind] ?? $current->kind }}.</strong>
        Создано {{ $current->created }} из {{ $current->requested }}@if ($current->failed), не удалось {{ $current->failed }}@endif.
        Страница обновится сама через несколько секунд.
    </div>
    <script>setTimeout(function () { window.location.reload(); }, 6000);</script>
@endif

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5>Что сейчас в игре</h5>
    <div class="row">
        <div class="col-md-4">
            <h6>Стартовый набор: {{ $release['starter_total']['have'] }} из {{ $release['starter_total']['target'] }}</h6>
            <table class="table table-sm">
                <thead><tr><th>Сложность</th><th class="text-end">Есть</th><th class="text-end">Нужно</th></tr></thead>
                <tbody>
                    @foreach ($release['starter'] as $level => $row)
                        <tr><td>{{ str_repeat('★', $level) }}</td><td class="text-end">{{ $row['have'] }}</td><td class="text-end">{{ $row['target'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-muted mb-0">Гостевых дел: {{ $release['guest']['have'] }} из {{ $release['guest']['target'] }} (берутся из самых простых).</p>
        </div>
        <div class="col-md-4">
            <h6>Резерв: {{ $release['reserve']['have'] }} из {{ $release['reserve']['target'] }}</h6>
            <p class="text-muted">Проверенные дела, которые ждут выпуска и не видны игрокам. Из резерва выходят ежедневные дела.</p>
            <h6>Сюжет «Путь детектива»: {{ $release['story']['have'] }} из {{ $release['story']['target'] }} глав</h6>
            <p class="text-muted">Дело каждой главы создаётся один раз кнопкой «Создать дела сюжета»; в каталоге они не видны.</p>
            <h6>Сегодня ({{ $release['today'] }}, Москва): {{ $release['released_today']['have'] }} из {{ $release['released_today']['target'] }}</h6>
            @if ($release['recent_days'])
                <table class="table table-sm">
                    <thead><tr><th>День выпуска</th><th class="text-end">Дел</th></tr></thead>
                    <tbody>
                        @foreach ($release['recent_days'] as $day => $total)
                            <tr><td>{{ \Illuminate\Support\Carbon::parse($day)->format('d.m.Y') }}</td><td class="text-end">{{ $total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted">Ежедневных дел ещё не выпускалось.</p>
            @endif
        </div>
        <div class="col-md-4">
            <h6>Как это работает</h6>
            <ul class="text-muted">
                <li>Зарегистрированным доступны все дела: 100 стартовых и ежедневные с момента выхода.</li>
                <li>Гостям — только гостевые дела стартового набора.</li>
                <li>«Выпустить сегодняшние дела» берёт 10 дел из резерва (если его не хватает, достраивает на ходу); повторное нажатие в тот же день ничего не добавит.</li>
                <li>Автоматического выпуска по расписанию нет: выпуск делается кнопкой (или командой <code>php artisan puzzles:release daily</code>, например из cron).</li>
            </ul>
        </div>
    </div>
</div>

<div class="bg-white rounded shadow-sm p-4 mb-3">
    <h5>Последние запуски</h5>
    @if ($runs->isEmpty())
        <p class="text-muted mb-0">Запусков ещё не было.</p>
    @else
        <table class="table table-sm mb-0">
            <thead><tr><th>Что</th><th>Состояние</th><th class="text-end">Создано</th><th class="text-end">Нужно</th><th class="text-end">Не вышло</th><th>Запущен</th><th>Кем</th><th>Примечание</th></tr></thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $labels[$run->kind] ?? $run->kind }}</td>
                        <td>{{ $states[$run->status] ?? $run->status }}</td>
                        <td class="text-end">{{ $run->created }}</td>
                        <td class="text-end">{{ $run->requested }}</td>
                        <td class="text-end">{{ $run->failed }}</td>
                        <td>{{ $run->started_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $run->actor_email ?? 'консоль' }}</td>
                        <td>{{ $run->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
