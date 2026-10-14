<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата · Whodoku</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3efe6; color: #26332d; line-height: 1.5; }
        main { max-width: 26rem; margin: 2rem auto; padding: 1.4rem 1rem; background: #fffaf0; border: 1px solid #d8cba8; border-radius: .8rem; }
        h1 { font-size: 1.3rem; margin: 0 0 .2rem; }
        .sum { font-size: 1.8rem; font-weight: 700; margin: .4rem 0 1rem; }
        .alpha { padding: .6rem .8rem; border-left: 4px solid #b29a63; background: rgb(178 154 99 / .18); margin: 1rem 0; }
        .row { display: flex; gap: .6rem; flex-wrap: wrap; }
        button { font: inherit; padding: .7rem 1.1rem; border-radius: .5rem; border: 1px solid #762634; cursor: pointer; flex: 1 1 8rem; }
        button[value=pay] { background: #762634; color: #fff; }
        button[value=cancel] { background: transparent; color: inherit; }
        @media (prefers-color-scheme: dark) {
            body { background: #151011; color: #ece5d6; }
            main { background: #2b2124; border-color: #4a3b3f; }
        }
    </style>
</head>
<body>
    <main>
        <h1>Оплата заказа</h1>
        <p>Whodoku · пакет подсказок</p>
        <p class="sum">{{ number_format($payment['amount'] / 100, 2, ',', ' ') }} ₽</p>
        <p class="alpha"><strong>Альфа-тестирование.</strong> Реальная оплата сейчас не применяется: деньги не списываются, подсказки вы получите бесплатно, чтобы проверить, как всё работает.</p>
        <form method="post" action="/shop/fake/{{ $id }}">
            @csrf
            <div class="row">
                <button name="result" value="pay">Оплатить</button>
                <button name="result" value="cancel">Отменить</button>
            </div>
        </form>
    </main>
</body>
</html>
