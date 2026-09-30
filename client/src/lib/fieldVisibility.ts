// Conditional-question visibility. Must stay identical to form_field_visibility() in
// server/config/form_fields.php.
//
// A field is VISIBLE iff show_if_field_id is null, OR (its parent is visible AND the parent's
// answer is one of show_if_value split on '|', trimmed, case-sensitive exact match).
// The answer is trimmed; a missing answer counts as ''. A missing parent or a cycle makes the field hidden.

export interface ConditionalField {
  id: number | string;
  show_if_field_id?: number | string | null;
  show_if_value?: string | null;
}

export function acceptedValues(raw: string | null | undefined): string[] {
  if (raw == null) return [];
  return String(raw)
    .split('|')
    .map(s => s.trim())
    .filter(s => s !== '');
}

export function computeVisibility(
  fields: ConditionalField[],
  values: Record<string, string>,
): Record<string, boolean> {
  const byId = new Map<number, ConditionalField>();
  fields.forEach(f => byId.set(Number(f.id), f));
  const memo = new Map<number, boolean>();

  const resolve = (id: number, path: Set<number>): boolean => {
    const cached = memo.get(id);
    if (cached !== undefined) return cached;
    if (path.has(id)) return false; // cycle
    const f = byId.get(id)!;
    const pid = f.show_if_field_id;
    if (pid === null || pid === undefined || pid === '') {
      memo.set(id, true);
      return true;
    }
    const parentId = Number(pid);
    let visible = false;
    if (byId.has(parentId)) {
      const nextPath = new Set(path).add(id);
      if (resolve(parentId, nextPath)) {
        const answer = String(values[String(parentId)] ?? '').trim();
        visible = acceptedValues(f.show_if_value).includes(answer);
      }
    }
    memo.set(id, visible);
    return visible;
  };

  const out: Record<string, boolean> = {};
  byId.forEach((_, id) => { out[String(id)] = resolve(id, new Set()); });
  return out;
}
