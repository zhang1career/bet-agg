{{--
    Reusable table header: one GET link toggling sort for a column (full page, immediate effect).

    Expected variables (pass via @include second argument):
    - routeName (string): named route, e.g. 'admin.games.index'
    - retainQuery (array<string, scalar>): base query parameters to preserve (typically request()->except('page'))
    - column (string): value for the `sort` query parameter
    - currentSort (string): active list `sort` value
    - currentDir (string): active list `dir` value ('asc'|'desc')
    - label (string): column title (caller should pass translated text)

    Optional:
    - ascTitle (string): `title` / `aria-label` when the control sorts ascending (first click or after desc)
    - descTitle (string): `title` / `aria-label` when the control switches to descending

    Behaviour:
    - Not currently sorted by `column`: show ↑, links to this column ascending.
    - This column ascending: show ↑, links to descending (toggle).
    - This column descending: show ↓, links to ascending (toggle).

    Filter forms should pass explicit hidden `sort` / `dir` (from controller) so Apply keeps sort.
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

    $onColumn = $currentSort === $column;
    if ($onColumn && $currentDir === 'desc') {
        $sortHref = $linkAsc;
        $glyph = '↓';
        $controlTitle = $ascTitle;
        $active = true;
    } elseif ($onColumn && $currentDir === 'asc') {
        $sortHref = $linkDesc;
        $glyph = '↑';
        $controlTitle = $descTitle;
        $active = true;
    } else {
        $sortHref = $linkAsc;
        $glyph = '↑';
        $controlTitle = $ascTitle;
        $active = false;
    }
@endphp
<th scope="col">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span>{{ $label }}</span>
        <span class="mall-th-sort d-inline-flex align-items-center">
            <a href="{{ $sortHref }}"
               class="mall-th-sort-link text-decoration-none {{ $active ? 'mall-th-sort-active' : '' }}"
               title="{{ $controlTitle }}"
               aria-label="{{ $controlTitle }}">{{ $glyph }}</a>
        </span>
    </div>
</th>
