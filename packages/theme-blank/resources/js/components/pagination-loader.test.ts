// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  loadFragment,
  appendCards,
  prependCardsAnchored,
  ScrollSpy,
} from './pagination-loader';

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

function makeFragmentHtml(
  cards: string[],
  {
    next,
    prev,
    canonical,
  }: { next?: string; prev?: string; canonical?: string } = {},
): string {
  const attrs = [
    next !== undefined ? ` data-next="${next}"` : '',
    prev !== undefined ? ` data-prev="${prev}"` : '',
    canonical !== undefined ? ` data-canonical="${canonical}"` : '',
  ].join('');
  return `<div class="catalog-product-grid-fragment"${attrs}>${cards.join('')}</div>`;
}

describe('pagination-loader', () => {
  describe('loadFragment', () => {
    it('it parses cards, next, prev and canonical from a fragment response', async () => {
      const html = makeFragmentHtml(
        ['<div class="card">Card 1</div>', '<div class="card">Card 2</div>'],
        {
          next: '/page?page=3',
          prev: '/page?page=1',
          canonical: '/page?page=2',
        },
      );

      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
          text: () => Promise.resolve(html),
        } as unknown as Response),
      );

      const result = await loadFragment('/page?page=2');

      expect(result.cards).toHaveLength(2);
      expect(result.next).toBe('/page?page=3');
      expect(result.prev).toBe('/page?page=1');
      expect(result.canonical).toBe('/page?page=2');
    });

    it('it returns empty cards when the fragment wrapper is missing', async () => {
      const html = '<div class="some-other-wrapper"><div class="card">Card 1</div></div>';

      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
          text: () => Promise.resolve(html),
        } as unknown as Response),
      );

      const result = await loadFragment('/page?page=2');

      expect(result.cards).toHaveLength(0);
      expect(result.next).toBeNull();
      expect(result.prev).toBeNull();
      expect(result.canonical).toBeNull();
    });
  });

  describe('appendCards', () => {
    it('it appends cloned cards to the grid', () => {
      const grid = document.createElement('div');
      document.body.appendChild(grid);

      const card1 = document.createElement('div');
      card1.className = 'card';
      card1.textContent = 'Card 1';
      const card2 = document.createElement('div');
      card2.className = 'card';
      card2.textContent = 'Card 2';

      appendCards(grid, [card1, card2]);

      expect(grid.children).toHaveLength(2);
      expect(grid.children[0]!.textContent).toBe('Card 1');
      expect(grid.children[1]!.textContent).toBe('Card 2');
    });
  });

  describe('prependCardsAnchored', () => {
    it('it prepends cards and compensates scrollTop so the viewport stays anchored', () => {
      const grid = document.createElement('div');
      const existing = document.createElement('div');
      existing.textContent = 'Existing';
      grid.appendChild(existing);
      document.body.appendChild(grid);

      // Fake scroller: scrollHeight grows after prepend
      let scrollHeightValue = 1000;
      const scroller = document.createElement('div');
      scroller.scrollTop = 300;

      let callCount = 0;
      Object.defineProperty(scroller, 'scrollHeight', {
        get(): number {
          // First call returns 1000, second call (after insert) returns 1200
          callCount += 1;
          return callCount === 1 ? 1000 : 1200;
        },
        configurable: true,
      });

      const card1 = document.createElement('div');
      card1.textContent = 'New Card 1';
      const card2 = document.createElement('div');
      card2.textContent = 'New Card 2';

      prependCardsAnchored(grid, [card1, card2], scroller);

      // scrollTop should be increased by the height delta (1200 - 1000 = 200)
      expect(scroller.scrollTop).toBe(300 + (1200 - 1000));

      // Cards should be prepended before the existing card
      expect(grid.children).toHaveLength(3);
      expect(grid.children[0]!.textContent).toBe('New Card 1');
      expect(grid.children[1]!.textContent).toBe('New Card 2');
      expect(grid.children[2]!.textContent).toBe('Existing');
    });
  });

  describe('ScrollSpy', () => {
    let rafCallback: FrameRequestCallback | null = null;

    beforeEach(() => {
      rafCallback = null;
      vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback): number => {
        rafCallback = cb;
        return 1;
      });
      vi.stubGlobal('cancelAnimationFrame', vi.fn());
    });

    function triggerScroll(scroller: EventTarget): void {
      scroller.dispatchEvent(new Event('scroll'));
      if (rafCallback) {
        rafCallback(0);
        rafCallback = null;
      }
    }

    it('it reports the topmost visible batch canonical via scroll spy', () => {
      const scroller = document.createElement('div');
      const onChange = vi.fn();
      const spy = new ScrollSpy(scroller, onChange);

      const card1 = document.createElement('div');
      const card2 = document.createElement('div');
      const card3 = document.createElement('div');
      document.body.appendChild(card1);
      document.body.appendChild(card2);
      document.body.appendChild(card3);

      // page1: card top at -500 (above viewport)
      // page2: card top at -100 (above viewport, closer to top)
      // page3: card top at 50 (below viewport top)
      card1.getBoundingClientRect = () => ({ top: -500 } as DOMRect);
      card2.getBoundingClientRect = () => ({ top: -100 } as DOMRect);
      card3.getBoundingClientRect = () => ({ top: 50 } as DOMRect);

      spy.addBatch('/page?page=1', card1);
      spy.addBatch('/page?page=2', card2);
      spy.addBatch('/page?page=3', card3);
      spy.start();

      triggerScroll(scroller);

      // Topmost batch at/above viewport top (top <= 0) that is the highest (least negative top)
      // card2 has top=-100 (above) and card1 has top=-500 (above), card3 has top=50 (below)
      // The "topmost visible" means the one whose firstCardEl is closest to viewport top from above
      // i.e. the batch with largest top that is still <= 0
      expect(onChange).toHaveBeenCalledWith('/page?page=2');
    });

    it('it only invokes the scroll-spy callback when the active page changes', () => {
      const scroller = document.createElement('div');
      const onChange = vi.fn();
      const spy = new ScrollSpy(scroller, onChange);

      const card1 = document.createElement('div');
      const card2 = document.createElement('div');
      document.body.appendChild(card1);
      document.body.appendChild(card2);

      // Both ticks: page1 is at/above top, page2 is below
      card1.getBoundingClientRect = () => ({ top: -100 } as DOMRect);
      card2.getBoundingClientRect = () => ({ top: 200 } as DOMRect);

      spy.addBatch('/page?page=1', card1);
      spy.addBatch('/page?page=2', card2);
      spy.start();

      // Tick 1: page1 is active
      triggerScroll(scroller);
      expect(onChange).toHaveBeenCalledTimes(1);
      expect(onChange).toHaveBeenLastCalledWith('/page?page=1');

      // Tick 2: same state — no change expected
      triggerScroll(scroller);
      expect(onChange).toHaveBeenCalledTimes(1);

      // Now page2 comes into view (page1 goes above, page2 is now at top)
      card1.getBoundingClientRect = () => ({ top: -300 } as DOMRect);
      card2.getBoundingClientRect = () => ({ top: -10 } as DOMRect);

      // Tick 3: page2 is now active
      triggerScroll(scroller);
      expect(onChange).toHaveBeenCalledTimes(2);
      expect(onChange).toHaveBeenLastCalledWith('/page?page=2');
    });

    it('it selects the physically topmost batch when scrolled above every batch', () => {
      const scroller = document.createElement('div');
      const onChange = vi.fn();
      const spy = new ScrollSpy(scroller, onChange);

      // Entry page (10) added first; page 9 prepended ABOVE it via "Load previous".
      const page10Card = document.createElement('div');
      const page9Card = document.createElement('div');
      document.body.appendChild(page10Card);
      document.body.appendChild(page9Card);

      // At the very top, both cards are below the viewport top (header/controls
      // sit above the grid). page 9 is physically higher → the smaller top.
      page9Card.getBoundingClientRect = () => ({ top: 120 } as DOMRect);
      page10Card.getBoundingClientRect = () => ({ top: 900 } as DOMRect);

      spy.addBatch('/catalog/category/1?page=10', page10Card);
      spy.addBatch('/catalog/category/1?page=9', page9Card);
      spy.start();

      triggerScroll(scroller);

      // Must NOT fall back to insertion order (page 10) — pick the topmost (page 9).
      expect(onChange).toHaveBeenCalledWith('/catalog/category/1?page=9');
    });
  });
});
