import type { ListColumn, ListMeta, MasterInfo } from '@/types';

/** Fills `:name`-style placeholders of a text the server sent. */
export function fill(
    template: string,
    values: Record<string, string | number>,
): string {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replaceAll(`:${key}`, String(value)),
        template,
    );
}

/**
 * What a table cell shows for a column key of a Resource row: related rows by
 * their name, lists joined, an empty list as the server's "empty" label.
 */
export function cellText(
    row: Record<string, unknown>,
    key: string,
    empty: Record<string, string> = {},
): string {
    const value = row[key];

    if (key === 'module') {
        return (value as { label: string } | undefined)?.label ?? '';
    }

    if (key === 'group' || key === 'department' || key === 'employment_type') {
        return (value as { name: string } | null | undefined)?.name ?? '';
    }

    if (key === 'platforms' || key === 'categories') {
        const names = ((value as { name: string }[] | undefined) ?? []).map(
            (item) => item.name,
        );

        return names.length > 0 ? names.join(', ') : (empty[key] ?? '');
    }

    return typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
        ? String(value)
        : '';
}

/** Columns from the server; one is sortable when the list declares a sort of the same key. */
export function tableColumns(info: MasterInfo, list: ListMeta): ListColumn[] {
    const sortable = new Set(list.sorts.map((sort) => sort.key));

    return info.columns.map((column) => ({
        key: column.key,
        label: column.label,
        ...(sortable.has(column.key) ? { sort: column.key } : {}),
    }));
}

/** The question asked before hiding or showing a row, from the server's texts and its usage count. */
export function impactDialog(
    info: MasterInfo,
    row: { name: string; usage_count: number },
    hiding: boolean,
): { title: string; message: string } {
    const texts = info.texts;

    if (!hiding) {
        return {
            title: fill(texts.impact_show_title, { name: row.name }),
            message: texts.impact_show,
        };
    }

    const usage =
        row.usage_count > 0
            ? fill(texts.impact_used, { usage: row.usage_count })
            : texts.impact_unused;

    return {
        title: fill(texts.impact_hide_title, { name: row.name }),
        message:
            row.usage_count === 0 && info.usage_notice
                ? `${usage} ${info.usage_notice}`
                : usage,
    };
}

/** A copy of the list with the item at `from` moved to `to` (out of range changes nothing). */
export function moveItem<T>(items: T[], from: number, to: number): T[] {
    if (
        from === to ||
        from < 0 ||
        to < 0 ||
        from >= items.length ||
        to >= items.length
    ) {
        return [...items];
    }

    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}
