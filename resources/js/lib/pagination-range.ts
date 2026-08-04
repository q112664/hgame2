export type PaginationRangeItem = number | 'ellipsis';

/**
 * Build a compact page range for large paginators.
 *
 * Examples (siblingCount = 1):
 * - 7 pages or fewer → [1, 2, 3, 4, 5, 6, 7]
 * - page 1 of 20 → [1, 2, 3, 'ellipsis', 20]
 * - page 10 of 20 → [1, 'ellipsis', 9, 10, 11, 'ellipsis', 20]
 * - page 20 of 20 → [1, 'ellipsis', 18, 19, 20]
 */
export function buildPaginationRange(
    currentPage: number,
    lastPage: number,
    siblingCount = 1,
): PaginationRangeItem[] {
    if (lastPage <= 1) {
        return lastPage === 1 ? [1] : [];
    }

    const current = Math.min(Math.max(Math.trunc(currentPage), 1), lastPage);
    const last = Math.trunc(lastPage);
    const siblings = Math.max(0, Math.trunc(siblingCount));

    // first + last + current + 2×siblings + 2 ellipsis slots
    const maxVisible = siblings * 2 + 5;

    if (last <= maxVisible) {
        return range(1, last);
    }

    const leftSibling = Math.max(current - siblings, 1);
    const rightSibling = Math.min(current + siblings, last);

    const showLeftEllipsis = leftSibling > 2;
    const showRightEllipsis = rightSibling < last - 1;

    if (!showLeftEllipsis && showRightEllipsis) {
        const leftItemCount = 3 + 2 * siblings;

        return [...range(1, leftItemCount), 'ellipsis', last];
    }

    if (showLeftEllipsis && !showRightEllipsis) {
        const rightItemCount = 3 + 2 * siblings;

        return [1, 'ellipsis', ...range(last - rightItemCount + 1, last)];
    }

    return [
        1,
        'ellipsis',
        ...range(leftSibling, rightSibling),
        'ellipsis',
        last,
    ];
}

function range(start: number, end: number): number[] {
    if (end < start) {
        return [];
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}
