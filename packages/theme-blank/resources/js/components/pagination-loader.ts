export interface FragmentResult {
  cards: Node[];
  next: string | null;
  prev: string | null;
  canonical: string | null;
}

export async function loadFragment(url: string): Promise<FragmentResult> {
  const response = await fetch(url);
  const htmlText = await response.text();

  const template = document.createElement('template');
  template.innerHTML = htmlText;

  const wrapper = template.content.querySelector('.catalog-product-grid-fragment');
  if (!wrapper) {
    return { cards: [], next: null, prev: null, canonical: null };
  }

  // Element children only — skip whitespace text nodes so callers can map
  // appended/prepended cards to grid.children by count without an off-by-N.
  const cards = Array.from(wrapper.children).map((node) => node.cloneNode(true));
  const next = (wrapper as HTMLElement).getAttribute('data-next');
  const prev = (wrapper as HTMLElement).getAttribute('data-prev');
  const canonical = (wrapper as HTMLElement).getAttribute('data-canonical');

  return { cards, next, prev, canonical };
}

export function appendCards(grid: Element, cards: Node[]): void {
  for (const card of cards) {
    grid.appendChild(card.cloneNode(true));
  }
}

export function prependCardsAnchored(
  grid: Element,
  cards: Node[],
  scroller?: Element,
): void {
  const scrollEl = scroller ?? document.scrollingElement;
  if (!scrollEl) {
    const firstChild = grid.firstChild;
    for (const card of cards) {
      grid.insertBefore(card.cloneNode(true), firstChild);
    }
    return;
  }

  const oldScrollTop = (scrollEl as HTMLElement).scrollTop;
  const oldScrollHeight = (scrollEl as HTMLElement).scrollHeight;

  const firstChild = grid.firstChild;
  for (const card of cards) {
    grid.insertBefore(card.cloneNode(true), firstChild);
  }

  const newScrollHeight = (scrollEl as HTMLElement).scrollHeight;
  (scrollEl as HTMLElement).scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
}

interface Batch {
  canonicalUrl: string;
  firstCardEl: Element;
}

export class ScrollSpy {
  #scroller: EventTarget;
  #onChange: (canonicalUrl: string) => void;
  #batches: Batch[] = [];
  #currentCanonical: string | null = null;
  #rafId: number | null = null;
  #pendingTick = false;
  #scrollHandler: () => void;

  constructor(
    scroller: EventTarget | undefined,
    onChange: (canonicalUrl: string) => void,
  ) {
    this.#scroller = scroller ?? window;
    this.#onChange = onChange;
    this.#scrollHandler = (): void => {
      if (!this.#pendingTick) {
        this.#pendingTick = true;
        this.#rafId = requestAnimationFrame(() => {
          this.#pendingTick = false;
          this.#rafId = null;
          this.#tick();
        });
      }
    };
  }

  addBatch(canonicalUrl: string, firstCardEl: Element): void {
    this.#batches.push({ canonicalUrl, firstCardEl });
  }

  start(): void {
    this.#scroller.addEventListener('scroll', this.#scrollHandler);
  }

  stop(): void {
    this.#scroller.removeEventListener('scroll', this.#scrollHandler);
    if (this.#rafId !== null) {
      cancelAnimationFrame(this.#rafId);
      this.#rafId = null;
    }
    this.#pendingTick = false;
  }

  #tick(): void {
    if (this.#batches.length === 0) {
      return;
    }

    // The active page is the one whose first card sits at/just above the
    // viewport top: among batches with getBoundingClientRect().top <= 0, the
    // one with the largest (least negative) top.
    let activeBatch: Batch | null = null;
    let activeTop = -Infinity;

    // Fallback for when the viewport is scrolled ABOVE every batch (e.g. at the
    // very top, with the header/controls above the grid): the physically
    // topmost batch — the one with the SMALLEST top — not insertion order.
    // (Insertion order would wrongly pick the entry page over pages prepended
    // above it via "Load previous".)
    let topmostBatch: Batch | null = null;
    let topmostTop = Infinity;

    for (const batch of this.#batches) {
      const top = batch.firstCardEl.getBoundingClientRect().top;
      if (top <= 0 && top > activeTop) {
        activeTop = top;
        activeBatch = batch;
      }
      if (top < topmostTop) {
        topmostTop = top;
        topmostBatch = batch;
      }
    }

    const selected = activeBatch ?? topmostBatch;

    if (selected !== null && selected.canonicalUrl !== this.#currentCanonical) {
      this.#currentCanonical = selected.canonicalUrl;
      this.#onChange(selected.canonicalUrl);
    }
  }
}
