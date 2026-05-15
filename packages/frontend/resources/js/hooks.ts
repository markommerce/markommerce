// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface HookRegistry {}

export type HookHandler<K extends keyof HookRegistry> = (
  payload: HookRegistry[K] extends { payload: infer P } ? P : never,
) =>
  | (HookRegistry[K] extends { return: infer R } ? R : never)
  | Promise<HookRegistry[K] extends { return: infer R } ? R : never>;

type HandlerEntry<K extends keyof HookRegistry> = {
  handler: HookHandler<K>;
  priority: number;
  order: number;
};

const registry = new Map<string, HandlerEntry<never>[]>();
let orderCounter = 0;

export function registerHook<K extends keyof HookRegistry>(
  name: K,
  handler: HookHandler<K>,
  options?: { priority?: number },
): void {
  const priority = options?.priority ?? 100;
  const entry = { handler, priority, order: orderCounter++ } as HandlerEntry<never>;
  const existing = registry.get(name as string) ?? [];
  existing.push(entry);
  registry.set(name as string, existing);
}

export async function runHook<K extends keyof HookRegistry>(
  name: K,
  payload: HookRegistry[K] extends { payload: infer P } ? P : never,
): Promise<HookRegistry[K] extends { return: infer R } ? R : never> {
  type PayloadType = HookRegistry[K] extends { payload: infer P } ? P : never;
  type ReturnType = HookRegistry[K] extends { return: infer R } ? R : never;

  const entries = registry.get(name as string) ?? [];
  const sorted = [...entries].sort((a, b) => {
    if (a.priority !== b.priority) return a.priority - b.priority;
    return a.order - b.order;
  });

  let current: PayloadType = payload;
  for (const entry of sorted) {
    current = await Promise.resolve(
      (entry.handler as HookHandler<K>)(current),
    ) as unknown as PayloadType;
  }

  return current as unknown as ReturnType;
}

export const Hooks: { register: typeof registerHook; run: typeof runHook } = {
  register: registerHook,
  run: runHook,
};

// For testing purposes only — resets all registered hooks
export function resetHooksForTesting(): void {
  registry.clear();
  orderCounter = 0;
}
