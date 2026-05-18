const warned = new WeakSet<HTMLElement>();

export function requireInnerControl(
  element: HTMLElement,
  selector: string,
): Element | null {
  const found = element.querySelector(selector);
  if (!found && !warned.has(element)) {
    warned.add(element);
    console.warn(
      `[${element.tagName.toLowerCase()}] expected a child matching "${selector}" but found none.`,
    );
  }
  return found;
}
