// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-infinite-scroll';
import { MkInfiniteScrollElement } from './mk-infinite-scroll';
import { MkElement } from '@markommerce/frontend';

// IntersectionObserver mock: captures callback, exposes triggerIntersect helper
let intersectCallback: IntersectionObserverCallback | null = null;
let observedTarget: Element | null = null;

class MockIntersectionObserver {
  constructor(cb: IntersectionObserverCallback) {
    intersectCallback = cb;
  }
  observe(el: Element): void {
    observedTarget = el;
  }
  unobserve(): void {}
  disconnect(): void {}
}

beforeAll(() => {
  vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);
  if (!customElements.get('mk-infinite-scroll')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  intersectCallback = null;
  observedTarget = null;
  vi.restoreAllMocks();
});

function makeFragment(
  cards: string[],
  nextUrl?: string,
  prevUrl?: string,
  canonical?: string,
): string {
  const dataNext = nextUrl ? ` data-next="${nextUrl}"` : '';
  const dataPrev = prevUrl ? ` data-prev="${prevUrl}"` : '';
  const dataCanonical = canonical ? ` data-canonical="${canonical}"` : '';
  return `<div class="catalog-product-grid-fragment"${dataNext}${dataPrev}${dataCanonical}>${cards.join('')}</div>`;
}

describe('mk-infinite-scroll', () => {
  it('it triggers a load when the sentinel intersects the viewport', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(makeFragment(['<div class="card">Card 1</div>'], '/catalog/category/1/page?page=3')),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    document.body.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    document.body.appendChild(el);
    await el.updateComplete;

    // Simulate sentinel entering the viewport
    expect(intersectCallback).not.toBeNull();
    intersectCallback!(
      [{ isIntersecting: true, target: observedTarget! } as IntersectionObserverEntry],
      new MockIntersectionObserver(intersectCallback!) as unknown as IntersectionObserver,
    );
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).toHaveBeenCalledWith('/catalog/category/1/page?page=2');
  });

  it('it exposes an accessible load-more fallback button', async () => {
    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    document.body.appendChild(el);
    await el.updateComplete;

    const btn = el.querySelector('button');
    expect(btn).not.toBeNull();
    // Must have accessible label
    const label = btn!.getAttribute('aria-label') ?? btn!.textContent?.trim();
    expect(label).toBeTruthy();
  });

  it('it auto-loads and appends the next page when the bottom sentinel intersects', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(
            ['<div class="card">Card A</div>'],
            '/catalog/category/1/page?page=3',
            undefined,
            '/catalog/category/1?page=3',
          ),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    const initialCard = document.createElement('div');
    initialCard.className = 'card';
    initialCard.textContent = 'Initial';
    grid.appendChild(initialCard);
    document.body.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    el.setAttribute('data-canonical', '/catalog/category/1?page=2');
    document.body.appendChild(el);
    await el.updateComplete;

    // Trigger sentinel intersection
    expect(intersectCallback).not.toBeNull();
    intersectCallback!(
      [{ isIntersecting: true, target: observedTarget! } as IntersectionObserverEntry],
      new MockIntersectionObserver(intersectCallback!) as unknown as IntersectionObserver,
    );
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).toHaveBeenCalledWith('/catalog/category/1/page?page=2');
    // Cards appended to grid
    const cards = grid.querySelectorAll('.card');
    expect(cards.length).toBeGreaterThan(1);
    // data-next updated
    expect(el.getAttribute('data-next')).toBe('/catalog/category/1/page?page=3');
  });

  it('it prepends the previous page anchored when the load-previous button is clicked', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(
            ['<div class="card">Prev Card</div>'],
            undefined,
            '/catalog/category/1/page?page=1',
            '/catalog/category/1?page=1',
          ),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const container = document.createElement('div');
    document.body.appendChild(container);

    // Load-previous button is a sibling BEFORE the grid
    const loadPrevBtn = document.createElement('button');
    loadPrevBtn.setAttribute('data-role', 'load-previous');
    loadPrevBtn.setAttribute('data-prev', '/catalog/category/1/page?page=2');
    loadPrevBtn.setAttribute('data-canonical', '/catalog/category/1?page=2');
    loadPrevBtn.textContent = 'Load previous';
    container.appendChild(loadPrevBtn);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    const existingCard = document.createElement('div');
    existingCard.className = 'card';
    existingCard.textContent = 'Current page card';
    grid.appendChild(existingCard);
    container.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=3');
    el.setAttribute('data-grid', '.catalog-product-grid');
    el.setAttribute('data-canonical', '/catalog/category/1?page=3');
    el.setAttribute('data-prev', '/catalog/category/1/page?page=2');
    container.appendChild(el);
    await el.updateComplete;

    // Click the load-previous button
    loadPrevBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).toHaveBeenCalledWith('/catalog/category/1/page?page=2');
    // Card prepended to grid
    const cards = grid.querySelectorAll('.card');
    expect(cards.length).toBe(2);
    expect(cards[0]!.textContent).toBe('Prev Card');
  });

  it('it does not auto-load a previous page on initial connect', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    const container = document.createElement('div');
    document.body.appendChild(container);

    // Load-previous button present
    const loadPrevBtn = document.createElement('button');
    loadPrevBtn.setAttribute('data-role', 'load-previous');
    loadPrevBtn.setAttribute('data-prev', '/catalog/category/1/page?page=1');
    loadPrevBtn.setAttribute('data-canonical', '/catalog/category/1?page=1');
    container.appendChild(loadPrevBtn);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    container.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=3');
    el.setAttribute('data-grid', '.catalog-product-grid');
    container.appendChild(el);
    await el.updateComplete;

    // Wait a tick — no auto-load should have happened
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('it removes the load-previous button when page one is reached', async () => {
    // Fragment with no data-prev means page 1 reached
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(
            ['<div class="card">Page 1 Card</div>'],
            undefined,
            undefined, // no prev — page 1
            '/catalog/category/1?page=1',
          ),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const container = document.createElement('div');
    document.body.appendChild(container);

    const loadPrevBtn = document.createElement('button');
    loadPrevBtn.setAttribute('data-role', 'load-previous');
    loadPrevBtn.setAttribute('data-prev', '/catalog/category/1/page?page=2');
    loadPrevBtn.setAttribute('data-canonical', '/catalog/category/1?page=2');
    container.appendChild(loadPrevBtn);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    container.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=3');
    el.setAttribute('data-grid', '.catalog-product-grid');
    el.setAttribute('data-prev', '/catalog/category/1/page?page=2');
    container.appendChild(el);
    await el.updateComplete;

    loadPrevBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // Button should be removed because no prev in fragment
    expect(container.querySelector('[data-role="load-previous"]')).toBeNull();
  });

  it('it replaceStates the canonical full-page url (never the fragment endpoint url)', async () => {
    const replaceStateSpy = vi.spyOn(history, 'replaceState');
    const pushStateSpy = vi.spyOn(history, 'pushState');

    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(
            ['<div class="card">Card B</div>'],
            '/catalog/category/1/page?page=3',
            undefined,
            '/catalog/category/1?page=3',
          ),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    const firstCard = document.createElement('div');
    firstCard.className = 'card';
    firstCard.textContent = 'First';
    grid.appendChild(firstCard);
    document.body.appendChild(grid);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    el.setAttribute('data-canonical', '/catalog/category/1?page=2');
    document.body.appendChild(el);
    await el.updateComplete;

    // Trigger forward load
    intersectCallback!(
      [{ isIntersecting: true, target: observedTarget! } as IntersectionObserverEntry],
      new MockIntersectionObserver(intersectCallback!) as unknown as IntersectionObserver,
    );
    await new Promise((r) => setTimeout(r, 0));

    // pushState must NOT have been called with a fragment URL
    expect(pushStateSpy).not.toHaveBeenCalled();

    // replaceState should NOT be called with a fragment endpoint URL
    for (const call of replaceStateSpy.mock.calls) {
      const url = call[2] as string;
      expect(url).not.toContain('/page?page=');
    }
  });

  it('it updates the url to the topmost batch canonical on scroll', async () => {
    const replaceStateSpy = vi.spyOn(history, 'replaceState');
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 0;
    });

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    document.body.appendChild(grid);

    // Add two cards: one from page 2 (above viewport) and one from page 3 (below)
    const cardPage2 = document.createElement('div');
    cardPage2.className = 'card';
    cardPage2.textContent = 'Page 2 card';
    grid.appendChild(cardPage2);

    const cardPage3 = document.createElement('div');
    cardPage3.className = 'card';
    cardPage3.textContent = 'Page 3 card';
    grid.appendChild(cardPage3);

    const el = document.createElement('mk-infinite-scroll') as MkInfiniteScrollElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=4');
    el.setAttribute('data-grid', '.catalog-product-grid');
    el.setAttribute('data-canonical', '/catalog/category/1?page=2');
    document.body.appendChild(el);
    await el.updateComplete;

    // Stub getBoundingClientRect: page2 card at top=-10 (above viewport), page3 card at top=100
    vi.spyOn(cardPage2, 'getBoundingClientRect').mockReturnValue({
      top: -10,
      bottom: 10,
      left: 0,
      right: 0,
      width: 0,
      height: 0,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    } as DOMRect);
    vi.spyOn(cardPage3, 'getBoundingClientRect').mockReturnValue({
      top: 100,
      bottom: 200,
      left: 0,
      right: 0,
      width: 0,
      height: 0,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    } as DOMRect);

    // Simulate scroll event on window
    window.dispatchEvent(new Event('scroll'));
    await new Promise((r) => setTimeout(r, 0));

    // replaceState should have been called with the canonical URL for page 2 (topmost batch)
    const canonicalCalls = replaceStateSpy.mock.calls.filter(
      (c) => (c[2] as string) === '/catalog/category/1?page=2',
    );
    expect(canonicalCalls.length).toBeGreaterThan(0);
  });
});
