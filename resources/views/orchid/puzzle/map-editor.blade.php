@error('map')
    <div class="alert alert-danger" role="alert">{{ $message }}</div>
@enderror
<div id="killdoku-map-editor" data-puzzle='@json($puzzle)' data-map='@json($map)' data-kinds='@json($kinds)'></div>
