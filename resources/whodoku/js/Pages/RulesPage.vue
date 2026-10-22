<script setup lang="ts">
import { useBrand } from '../composables/useBrand';
import MiniMap from '../Components/MiniMap.vue';
import TutorialCase from '../Components/TutorialCase.vue';
import { CRIME_FINDS, CRIME_RULE, puzzleTypeLabel } from '../composables/puzzleTypes';

const brand = useBrand();

// Cell code: room letter, icon (c armchair, p plant, r rug) and mark (+ suits, - does not, capital letter = guest).
const terms: { title: string; text: string; maps: { rows: string[][]; caption: string; windows?: { row: number; col: number; side: 'north' | 'south' | 'west' | 'east' }[] }[] }[] = [
    {
        title: 'Строка и столбец',
        text: 'Строка — горизонтальная линия клеток, столбец — вертикальная. В каждой строке и в каждом столбце стоит не больше одного гостя: как в судоку, двое на одной линии не пересекаются.',
        maps: [
            { caption: 'строка', rows: [['a..', 'a..', 'a..'], ['a.+', 'a.+', 'a.+'], ['a..', 'a..', 'a..']] },
            { caption: 'столбец', rows: [['a..', 'a.+', 'a..'], ['a..', 'a.+', 'a..'], ['a..', 'a.+', 'a..']] },
        ],
    },
    {
        title: 'В комнате',
        text: 'Комната — область одного цвета с толстой границей. «Я стояла в холле», «Я ждал в кабинете» — подходит любая клетка этой комнаты.',
        maps: [{ caption: 'комната слева сверху', rows: [['a.+', 'a.+', 'b..'], ['a.+', 'a.+', 'b..'], ['b..', 'b..', 'b..']] }],
    },
    {
        title: 'Один в комнате',
        text: '«Я был один в холле»: в этой комнате больше нет ни одного гостя — ни в одной другой её клетке никто не стоит.',
        maps: [{ caption: 'гость А один', rows: [['a.A', 'a.-', 'b..'], ['a.-', 'a.-', 'b..'], ['b..', 'b..', 'b..']] }],
    },
    {
        title: 'В углу комнаты',
        text: 'Угол — клетка, где сходятся две стороны границы комнаты. В прямоугольной комнате это четыре клетки по краям.',
        maps: [{ caption: 'углы комнаты', rows: [['a.+', 'a..', 'a.+'], ['a..', 'a..', 'a..'], ['a.+', 'a..', 'a.+']] }],
    },
    {
        title: 'Рядом с предметом',
        text: 'Так звучат «Я стоял рядом с…», «Я разглядывал…», «Я любовался…», «Я задержался у…». Гость на клетке прямо над, под, слева или справа от предмета и в той же комнате. По диагонали — не рядом, а границу комнаты и стену перешагнуть нельзя.',
        maps: [{ caption: 'предмет в центре', rows: [['a..', 'a.+', 'b..'], ['a.+', 'ap.', 'b.-'], ['a..', 'a.+', 'b..']] }],
    },
    {
        title: 'Выше, ниже, левее, правее',
        text: '«Выше предмета» — любая клетка в строках над ним, «правее» — в столбцах правее него. Подходят клетки всех комнат.',
        maps: [
            { caption: 'выше предмета', rows: [['a.+', 'a.+', 'a.+'], ['a.+', 'a.+', 'a.+'], ['a.-', 'ap.', 'a.-']] },
            { caption: 'правее предмета', rows: [['a..', 'a.+', 'a.+'], ['a..', 'a.+', 'a.+'], ['ap.', 'a.+', 'a.+']] },
        ],
    },
    {
        title: 'На предмете',
        text: 'Гость сидит, лежит или стоит ровно на клетке предмета («Я сидел на табурете», «Я лежал на кровати»). Это возможно только на используемых предметах (кресло, диван, ковёр); на шкафу, рояле или растении стоять нельзя — такая клетка занята целиком.',
        maps: [{ caption: 'кресло — можно, растение — нельзя', rows: [['a..', 'a..', 'a..'], ['ac+', 'a..', 'ap-'], ['a..', 'a..', 'a..']] }],
    },
    {
        title: 'Блокнот',
        text: 'Место для догадок: пометки не ставят гостя на карту и не проверяются. Буква гостя на клетке — «возможно здесь», крестик — «здесь точно нет». Помощник обводит клетки, где гость ещё может стоять по своему показанию и уже перекрытым линиям, а «Отметить кандидатов» превращает их в заметки. Блокнот, автоотметку линий (перекрытые строки и столбцы) и помощника открывает навык «Экспертность»: каждый новый уровень детектива даёт очко развития, которое вкладывают в навыки в профиле (интеллект открывает сложные дела, внимательность — подсказки и подсветку на карте).',
        maps: [{ caption: 'Б — возможно, × — нет', rows: [['a..', 'a.+', 'a..'], ['a.-', 'a..', 'a.+'], ['a..', 'a.-', 'a..']] }],
    },
    {
        title: 'Возле окна',
        text: 'Окно стоит на линии между клетками или на краю карты. Подходит клетка, к которой окно прилегает; если окно на границе двух комнат — обе клетки по сторонам линии.',
        maps: [{
            caption: 'окно на краю и на границе комнат',
            rows: [['a.+', 'a..', 'b..'], ['a..', 'a.+', 'b.+'], ['a..', 'a..', 'b..']],
            windows: [{ row: 0, col: 0, side: 'north' }, { row: 1, col: 1, side: 'east' }],
        }],
    },
];
</script>

<template>
    <main class="public-page rules-page">
        <header class="public-header">
            <a href="/" class="site-home-link">{{ brand }}</a>
            <a href="/puzzles" class="brand">Whodoku</a>
            <a href="/puzzles" class="quiet-link">К делам</a>
        </header>

        <section class="catalog-intro">
            <p class="eyebrow">Правила</p>
            <h1>Как раскрывать дела</h1>
            <p>Расставьте гостей по показаниям, найдите единственную пустую клетку и по ней определите виновного.</p>
        </section>

        <TutorialCase />

        <section aria-labelledby="terms-heading">
            <h2 id="terms-heading">Пояснения к терминам</h2>
            <p>Зелёная галочка — клетка подходит, красный крестик — не подходит. Тёмный круг с буквой — гость.</p>
            <div class="term-grid">
                <article v-for="term in terms" :key="term.title" class="term-card">
                    <h3>{{ term.title }}</h3>
                    <div class="term-maps">
                        <MiniMap v-for="map in term.maps" :key="map.caption" :rows="map.rows" :windows="map.windows" :caption="map.caption" />
                    </div>
                    <p>{{ term.text }}</p>
                </article>
            </div>
        </section>

        <section aria-labelledby="culprit-heading">
            <h2 id="culprit-heading">Как найти виновного</h2>
            <p>Когда все гости стоят на местах, остаётся одна пустая клетка — там находка. {{ CRIME_RULE }} Что именно лежит на пустой клетке, зависит от дела:</p>
            <ul class="crime-rules">
                <li v-for="item in CRIME_FINDS" :key="item.type"><strong>{{ puzzleTypeLabel(item.type) }}.</strong> {{ item.find }}.</li>
            </ul>
        </section>

        <p><a class="primary-action" href="/puzzles">К делам <span aria-hidden="true">→</span></a></p>
    </main>
</template>
