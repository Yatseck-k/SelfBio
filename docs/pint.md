## Инструкция Pint:


- Проверить только нарушения (без правок):
  ```bash
  sail pint --test
  ```
- Отформатировать только изменённые файлы:
  ```bash
  sail pint --dirty
  ```
- Вывести подробный отчёт:
  ```bash
  sail pint -v
  ```
- Отформатировать выбранную папку или файл:
  ```bash
  sail pint app/Http/Controllers
  sail pint app/Http/Controllers/SomeController.php
  ```

- Форматировать весь проект:
  ```bash
  sail pint
  ```
