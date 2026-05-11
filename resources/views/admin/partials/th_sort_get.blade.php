{{--
    Reusable table header: GET links for ascending / descending sort (full navigation, immediate effect).

    Expected variables (pass via @include second argument):
    - routeName (string): named route, e.g. 'admin.games.index'
    - retainQuery (array<string, scalar>): base query string parameters to preserve (typically request()->except('page'))
    - column (string): value for the `sort` query parameter for this column
    - currentSort (string): active list `sort` value
    - currentDir (string): active list `dir` value ('asc'|'desc')
    - label (string): column title (caller should pass translated text)

    Optional:
    - ascTitle (string): `title` / `aria-label` on the ascending control
    - descTitle (string): `title` / `aria-label` on the descending control

    Filter forms on the same page should submit hidden inputs (or include sort/dir in retainQuery when building
    hidden fields) so "Apply filters" keeps the current sort — usually by emitting request()->except('page') for
    keys not overridden by explicit filter controls.
--}}
@php
    /** @var string $routeName */
    /** @var array<string, mixed> $retainQuery */
    /** @var string $column */
    /** @var string $currentSort */
    /** @var string $currentDir */
    /** @var string $label */
    $ascTitle = $ascTitle ?? __('console.partial_th_sort.asc');
    $descTitle = $descTitle ?? __('console.partial_th_sort.desc');
    $linkAsc = route($routeName, array_merge($retainQuery, ['sort' => $column, 'dir' => 'asc', 'page' => 1]));
    $linkDesc = route($routeName, array_merge($retainQuery, ['sort' => $column, 'dir' => 'desc', 'page' => 1]));
    $activeAsc = $currentSort === $column && $currentDir === 'asc';
    $activeDesc = $currentSort === $column && $currentDir === 'desc';
@endphp
<th scope="col">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span>{{ $label }}</span>
        <span class="mall-th-sort d-inline-flex align-items-center gap-1">
            <a href="{{ $linkAsc }}"
               class="mall-th-sort-link text-decoration-none {{ $activeAsc ? 'mall-th-sort-active' : '' }}"
               title="{{ $ascTitle }}"
               aria-label="{{ $ascTitle }}">↑</a>
            <a href="{{ $linkDesc }}"
               class="mall-th-sort-link text-decoration-none {{ $activeDesc ? 'mall-th-sort-active' : '' }}"
               title="{{ $descTitle }}"
               aria-label="{{ $descTitle }}">↓</a>
        </span>
    </div>
</th>
