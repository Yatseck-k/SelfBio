## Инструкция Pint:

- Проверить только нарушения (без правок):
  ```bash
  docker compose exec php vendor/bin/pint --test
  ```
- Отформатировать только изменённые файлы:
  ```bash
  docker compose exec php vendor/bin/pint --dirty
  ```
- Вывести подробный отчёт:
  ```bash
  docker compose exec php vendor/bin/pint -v
  ```
- Отформатировать выбранную папку или файл:
  ```bash
  docker compose exec php vendor/bin/pint app/Http/Controllers
  docker compose exec php vendor/bin/pint app/Http/Controllers/SomeController.php
  ```
- Форматировать весь проект:
  ```bash
  docker compose exec php vendor/bin/pint
  ```
