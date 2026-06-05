// @vitest-environment happy-dom
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineAllComponents } from '@markommerce/frontend';
import './mk-load-more';
import { MkLoadMoreElement } from './mk-load-more';

beforeAll(() => {
  if (!customElements.get('mk-load-more')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

function makeFragment(
  cards: string[],
  options: { nextUrl?: string; prevUrl?: string; canonical?: string } = {},
): string {
  const dataNext = options.nextUrl ? ` data-next="${options.nextUrl}"` : '';
  const dataPrev = options.prevUrl ? ` data-prev="${options.prevUrl}"` : '';
  const dataCanonical = options.canonical ? ` data-canonical="${options.canonical}"` : '';
  return `<div class="catalog-product-grid-fragment"${dataNext}${dataPrev}${dataCanonical}>${cards.join('')}</div>`;
}

function stubBoundingRect(el: Element, top: number): void {
  vi.spyOn(el, 'getBoundingClientRect').mockReturnValue({
    top,
    bottom: top + 100,
    left: 0,
    right: 100,
    width: 100,
    height: 100,
    x: 0,
    y: top,
    toJSON: () => ({}),
  });
}

function stubRequestAnimationFrame(): void {
  vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
    cb(0);
    return 0;
  });
  vi.stubGlobal('cancelAnimationFrame', () => {});
}

interface SetupOptions {
  nextUrl?: string;
  prevUrl?: string;
  canonical?: string;
  existingCards?: string[];
}

function setupDom(options: SetupOptions = {}): {
  grid: HTMLElement;
  el: MkLoadMoreElement;
  loadMoreBtn: HTMLButtonElement;
  loadPreviousBtn: HTMLButtonElement;
} {
  const {
    nextUrl = '/catalog/category/1/page?page=2',
    prevUrl,
    canonical = '/catalog/category/1?page=1',
    existingCards = ['<div class="card">Existing Card</div>'],
  } = options;

  const grid = document.createElement('div');
  grid.className = 'catalog-product-grid';
  grid.innerHTML = existingCards.join('');

  const loadPreviousBtn = document.createElement('button');
  loadPreviousBtn.setAttribute('data-role', 'load-previous');
  if (!prevUrl) {
    loadPreviousBtn.style.display = 'none';
  }

  const el = document.createElement('mk-load-more') as MkLoadMoreElement;
  if (nextUrl) {
    el.setAttribute('data-next', nextUrl);
  }
  if (prevUrl) {
    el.setAttribute('data-prev', prevUrl);
  }
  el.setAttribute('data-canonical', canonical);
  el.setAttribute('data-grid', '.catalog-product-grid');

  const loadMoreBtn = document.createElement('button');
  loadMoreBtn.setAttribute('data-role', 'load-more');
  loadMoreBtn.textContent = 'Load more';
  el.appendChild(loadMoreBtn);

  document.body.appendChild(loadPreviousBtn);
  document.body.appendChild(grid);
  document.body.appendChild(el);

  return { grid, el, loadMoreBtn, loadPreviousBtn };
}

describe('mk-load-more', () => {
  // Kept from original (adapted to new loadFragment/appendCards path)
  it('it fetches the next page fragment from the data-next url', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">Card 1</div>'], {
            nextUrl: '/catalog/category/1/page?page=3',
            canonical: '/catalog/category/1?page=2',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { loadMoreBtn } = setupDom({ nextUrl: '/catalog/category/1/page?page=2' });

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).toHaveBeenCalledWith('/catalog/category/1/page?page=2');
  });

  // Kept from original (adapted to new loadFragment/appendCards path)
  it('it appends the returned cards to the existing grid', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(
            ['<div class="card">Card A</div>', '<div class="card">Card B</div>'],
            {
              nextUrl: '/catalog/category/1/page?page=3',
              canonical: '/catalog/category/1?page=2',
            },
          ),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { loadMoreBtn } = setupDom({ nextUrl: '/catalog/category/1/page?page=2' });

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(document.body.textContent).toContain('Card A');
    expect(document.body.textContent).toContain('Card B');
  });

  // REWRITTEN: was asserting pushState; now asserts replaceState with canonical url
  it('it replaceStates the canonical full-page url (never the fragment endpoint url)', async () => {
    const replaceStateMock = vi.spyOn(history, 'replaceState');
    stubRequestAnimationFrame();

    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">Card 1</div>'], {
            canonical: '/catalog/category/1?page=2',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { loadMoreBtn, grid } = setupDom({
      nextUrl: '/catalog/category/1/page?page=2',
      canonical: '/catalog/category/1?page=1',
    });

    // Stub bounding rect on the existing card so initial batch fires
    const existingCard = grid.querySelector('.card') as Element;
    stubBoundingRect(existingCard, -100);

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // Simulate scroll to trigger scroll-spy
    const newCard = grid.querySelectorAll('.card')[1] as Element | undefined;
    if (newCard) {
      stubBoundingRect(newCard, -50);
    }

    window.dispatchEvent(new Event('scroll'));
    await new Promise((r) => setTimeout(r, 0));

    // replaceState must have been called with canonical URL, never the fragment endpoint
    const calls = replaceStateMock.mock.calls;
    expect(calls.length).toBeGreaterThan(0);
    const urls = calls.map((c) => c[2] as string);
    expect(urls.some((url) => url.includes('/page?page='))).toBe(false);
    expect(urls.some((url) => url === '/catalog/category/1?page=2' || url === '/catalog/category/1?page=1')).toBe(true);
  });

  // REWRITTEN: was asserting el.remove(); now asserts load-more button removed, element stays
  it('it removes the load-more button when the last page is reached', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">Last Card</div>'], {
            canonical: '/catalog/category/1?page=5',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { el, loadMoreBtn } = setupDom({
      nextUrl: '/catalog/category/1/page?page=5',
      canonical: '/catalog/category/1?page=4',
    });

    expect(document.body.contains(el)).toBe(true);
    expect(document.body.contains(loadMoreBtn)).toBe(true);

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // Element stays connected (scroll-spy + load-previous)
    expect(document.body.contains(el)).toBe(true);
    // Load-more button is removed (no more next pages)
    expect(document.body.contains(loadMoreBtn)).toBe(false);
    // data-next attribute is removed
    expect(el.hasAttribute('data-next')).toBe(false);
  });

  it('it appends the next page and updates data-next when load more is clicked', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">New Card</div>'], {
            nextUrl: '/catalog/category/1/page?page=3',
            canonical: '/catalog/category/1?page=2',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { el, loadMoreBtn, grid } = setupDom({
      nextUrl: '/catalog/category/1/page?page=2',
      canonical: '/catalog/category/1?page=1',
    });

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // Card appended to grid
    expect(grid.textContent).toContain('New Card');
    // data-next updated to fragment's data-next
    expect(el.getAttribute('data-next')).toBe('/catalog/category/1/page?page=3');
  });

  it('it prepends the previous page anchored when load previous is clicked', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">Prev Card</div>'], {
            canonical: '/catalog/category/1?page=1',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { el, grid, loadPreviousBtn } = setupDom({
      nextUrl: '/catalog/category/1/page?page=3',
      prevUrl: '/catalog/category/1/page?page=1',
      canonical: '/catalog/category/1?page=2',
      existingCards: ['<div class="card">Existing Card</div>'],
    });

    // Stub scroll metrics
    const scroller = document.scrollingElement as HTMLElement;
    Object.defineProperty(scroller, 'scrollHeight', {
      get: vi.fn().mockReturnValueOnce(500).mockReturnValue(700),
      configurable: true,
    });
    scroller.scrollTop = 100;

    loadPreviousBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // Prev card prepended (appears before existing)
    const cards = grid.querySelectorAll('.card');
    expect(cards[0].textContent).toBe('Prev Card');
    expect(cards[1].textContent).toBe('Existing Card');
    // data-prev removed (no more prev in fragment response)
    expect(el.hasAttribute('data-prev')).toBe(false);
  });

  it('it removes the load-previous button when page one is reached', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          // No data-prev in fragment = page 1 reached
          makeFragment(['<div class="card">First Card</div>'], {
            canonical: '/catalog/category/1?page=1',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { loadPreviousBtn } = setupDom({
      nextUrl: '/catalog/category/1/page?page=3',
      prevUrl: '/catalog/category/1/page?page=1',
      canonical: '/catalog/category/1?page=2',
    });

    expect(document.body.contains(loadPreviousBtn)).toBe(true);

    loadPreviousBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(document.body.contains(loadPreviousBtn)).toBe(false);
  });

  it('it updates the url to the topmost batch canonical on scroll', async () => {
    const replaceStateMock = vi.spyOn(history, 'replaceState');
    stubRequestAnimationFrame();

    const fetchMock = vi.fn().mockResolvedValue({
      text: () =>
        Promise.resolve(
          makeFragment(['<div class="card">New Card</div>'], {
            nextUrl: '/catalog/category/1/page?page=3',
            canonical: '/catalog/category/1?page=2',
          }),
        ),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const { loadMoreBtn, grid } = setupDom({
      nextUrl: '/catalog/category/1/page?page=2',
      canonical: '/catalog/category/1?page=1',
    });

    // First card at -200 (above viewport)
    const firstCard = grid.querySelector('.card') as Element;
    stubBoundingRect(firstCard, -200);

    loadMoreBtn.click();
    await new Promise((r) => setTimeout(r, 0));

    // New card at -50 (more recently scrolled into view)
    const newCard = grid.querySelectorAll('.card')[1] as Element | undefined;
    if (newCard) {
      stubBoundingRect(newCard, -50);
    }

    // Trigger scroll
    window.dispatchEvent(new Event('scroll'));
    await new Promise((r) => setTimeout(r, 0));

    // Should have called replaceState with page 2 canonical (topmost visible batch)
    const calls = replaceStateMock.mock.calls;
    const urls = calls.map((c) => c[2] as string);
    expect(urls).toContain('/catalog/category/1?page=2');
  });
});
