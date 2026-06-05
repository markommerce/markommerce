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

function makeFragment(cards: string[], nextUrl?: string): string {
  const dataNext = nextUrl ? ` data-next="${nextUrl}"` : '';
  return `<div class="catalog-product-grid-fragment"${dataNext}>${cards.join('')}</div>`;
}

describe('mk-infinite-scroll', () => {
  it('it triggers a load when the sentinel intersects the viewport', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () => Promise.resolve(makeFragment(['<div class="card">Card 1</div>'], '/catalog/category/1/page?page=3')),
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
});
