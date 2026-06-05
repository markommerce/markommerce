// @vitest-environment happy-dom
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-load-more';
import { MkLoadMoreElement } from './mk-load-more';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-load-more')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

function makeFragment(cards: string[], nextUrl?: string): string {
  const dataNext = nextUrl ? ` data-next="${nextUrl}"` : '';
  return `<div class="catalog-product-grid-fragment"${dataNext}>${cards.join('')}</div>`;
}

describe('mk-load-more', () => {
  it('it fetches the next page fragment from the data-next url', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () => Promise.resolve(makeFragment(['<div class="card">Card 1</div>'], '/catalog/category/1/page?page=2')),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    document.body.appendChild(grid);

    const el = document.createElement('mk-load-more') as MkLoadMoreElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    const btn = document.createElement('button');
    btn.textContent = 'Load more';
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    btn.click();
    await el.updateComplete;
    // Wait for async fetch
    await new Promise((r) => setTimeout(r, 0));

    expect(fetchMock).toHaveBeenCalledWith('/catalog/category/1/page?page=2');
  });

  it('it appends the returned cards to the existing grid', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      text: () => Promise.resolve(makeFragment(['<div class="card">Card A</div>', '<div class="card">Card B</div>'], '/catalog/category/1/page?page=3')),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    const existingCard = document.createElement('div');
    existingCard.className = 'card';
    existingCard.textContent = 'Existing';
    grid.appendChild(existingCard);
    document.body.appendChild(grid);

    const el = document.createElement('mk-load-more') as MkLoadMoreElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=3');
    el.setAttribute('data-grid', '.catalog-product-grid');
    const btn = document.createElement('button');
    btn.textContent = 'Load more';
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    btn.click();
    await new Promise((r) => setTimeout(r, 0));

    const cards = grid.querySelectorAll('.card');
    expect(cards).toHaveLength(3);
    expect(grid.textContent).toContain('Existing');
    expect(grid.textContent).toContain('Card A');
    expect(grid.textContent).toContain('Card B');
  });

  it('it updates the browser url to the next page via history api', async () => {
    const pushStateMock = vi.spyOn(history, 'pushState');
    const fetchMock = vi.fn().mockResolvedValue({
      text: () => Promise.resolve(makeFragment(['<div class="card">Card 1</div>'], '/catalog/category/1/page?page=3')),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    document.body.appendChild(grid);

    const el = document.createElement('mk-load-more') as MkLoadMoreElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    btn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(pushStateMock).toHaveBeenCalledWith(null, '', '/catalog/category/1/page?page=3');
  });

  it('it removes the control when there is no next page', async () => {
    // Fragment with no data-next attribute means last page
    const fetchMock = vi.fn().mockResolvedValue({
      text: () => Promise.resolve(makeFragment(['<div class="card">Last Card</div>'])),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    const grid = document.createElement('div');
    grid.className = 'catalog-product-grid';
    document.body.appendChild(grid);

    const el = document.createElement('mk-load-more') as MkLoadMoreElement;
    el.setAttribute('data-next', '/catalog/category/1/page?page=2');
    el.setAttribute('data-grid', '.catalog-product-grid');
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(document.body.contains(el)).toBe(true);

    btn.click();
    await new Promise((r) => setTimeout(r, 0));

    expect(document.body.contains(el)).toBe(false);
  });
});
