@if (!empty($post['image']))
    <div class="mb-3">
        <p class="mb-2">Текущее изображение</p>
        <img src="{{ Storage::disk('public')->url($post['image']) }}" alt="" style="max-width: 320px; height: auto">
    </div>
@endif
