@foreach($games as $g)
    <option value="{{ $g->id }}" @selected(isset($selectedGameId) && (int) $selectedGameId === (int) $g->id)>{{ $gameSelectLabels[$g->id] ?? 'Untitled' }}</option>
@endforeach
