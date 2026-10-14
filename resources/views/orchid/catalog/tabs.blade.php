{{-- Only the chosen list is drawn: the three tables together had 60 rows of menus and took a third of a second. --}}
<ul class="nav nav-tabs mb-3">
    @foreach (['kinds' => 'Виды предметов', 'names' => 'Названия', 'themes' => 'Комнаты и их мебель'] as $key => $title)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('platform.objects', $key === 'kinds' ? [] : ['tab' => $key]) }}">{{ $title }}</a>
        </li>
    @endforeach
</ul>
