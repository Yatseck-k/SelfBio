<div class="bg-white rounded shadow-sm p-4">
    <h5>{{ $puzzle['title'] }} <small class="text-muted">({{ $puzzle['slug'] }})</small></h5>
    <p>Статус дела: <strong>{{ $puzzle['status'] }}</strong></p>
    <p>Статус solver: <strong>{{ $solverStatus }}</strong></p>

    @if (! isset($validation['status']))
        <p>Проверка ещё не запускалась.</p>
    @endif

    <h6>Ошибки структуры</h6>
    @if (($validation['structural_errors'] ?? []) === [])
        <p>Ошибок нет.</p>
    @else
        <ul>
            @foreach ($validation['structural_errors'] as $issue)
                <li><code>{{ $issue['code'] }}</code> — {{ $issue['path'] }}</li>
            @endforeach
        </ul>
    @endif

    <h6>Ошибки авторского решения</h6>
    @if (($validation['author_solution_errors'] ?? []) === [])
        <p>Ошибок нет.</p>
    @else
        <ul>
            @foreach ($validation['author_solution_errors'] as $issue)
                <li><code>{{ $issue['code'] }}</code> — {{ $issue['path'] }}</li>
            @endforeach
        </ul>
    @endif

    <hr>
    <h5>Полная проверка уровня</h5>
    <p class="text-muted">Бот проверяет структуру карты, все показания, условия дела, единственность решения, понятность формулировок и прохождение уровня как игроком.</p>
    @if (($botReview['checks'] ?? []) === [])
        <p>Полная проверка ещё не запускалась.</p>
    @else
        <p>
            Результат:
            <strong class="{{ ($botReview['passed'] ?? false) ? 'text-success' : 'text-danger' }}">
                {{ ($botReview['passed'] ?? false) ? 'пройдено' : 'найдены ошибки' }}
            </strong>
            · шагов логики: {{ $botReview['steps'] ?? 0 }}
            · самый сложный приём: {{ $botReview['hardest_level'] ?? 0 }}
        </p>
        <ul class="mb-4">
            @foreach ($botReview['checks'] as $check)
                <li>
                    @if ($check['passed'])
                        <span class="text-success">✓</span>
                    @elseif (($check['severity'] ?? 'error') === 'warning')
                        <span class="text-warning">!</span>
                    @else
                        <span class="text-danger">✗</span>
                    @endif
                    <strong>{{ $check['title'] }}</strong>
                    @if (($check['message'] ?? '') !== '') — {{ $check['message'] }} @endif
                </li>
            @endforeach
        </ul>
    @endif

    <h6>Показания ({{ count($statements) }})</h6>
    <ol>
        @forelse ($statements as $statement)
            <li><code>{{ $statement['type'] }}</code> — {{ $statement['text'] }}</li>
        @empty
            <li>Показаний нет.</li>
        @endforelse
    </ol>

    <h6>Условия дела ({{ count($conditions) }})</h6>
    <ul>
        @forelse ($conditions as $condition)
            <li><code>{{ $condition['type'] }}</code> — {{ $condition['text'] }}</li>
        @empty
            <li>В этом уровне нет дополнительных условий.</li>
        @endforelse
    </ul>
</div>
