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
</div>
