/** A copy of the list with the item at `index` moved one place up or down. */
export function moveItem<T>(list: T[], index: number, step: -1 | 1): T[] {
    const target = index + step;

    if (target < 0 || target >= list.length) return list;

    const copy = [...list];
    [copy[index], copy[target]] = [copy[target], copy[index]];

    return copy;
}

/** A copy of the list without the item at `index`. */
export function removeItem<T>(list: T[], index: number): T[] {
    return list.filter((_, position) => position !== index);
}
