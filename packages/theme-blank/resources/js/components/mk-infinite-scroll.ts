import { html } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import { loadFragment, appendCards, prependCardsAnchored, ScrollSpy } from './pagination-loader';

export class MkInfiniteScrollElement extends MkElement {
  #observer: IntersectionObserver | null = null;
  #sentinel: HTMLElement | null = null;
  #scrollSpy: ScrollSpy | null = null;
  #scroller: Element | undefined = undefined;

  override connectedCallback(): void {
    super.connectedCallback();
    this.#ensureFallbackButton();
    this.#setupScrollSpy();
    this.#setupSentinel();
    this.#bindLoadPrevious();
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    this.#observer?.disconnect();
    this.#observer = null;
    this.#scrollSpy?.stop();
    this.#scrollSpy = null;
  }

  #getGrid(): Element | null {
    const gridSelector = this.getAttribute('data-grid');
    return gridSelector ? document.querySelector(gridSelector) : null;
  }

  #getLoadPreviousButton(): Element | null {
    const grid = this.#getGrid();
    return grid?.parentElement?.querySelector('[data-role="load-previous"]') ?? null;
  }

  #ensureFallbackButton(): void {
    if (!this.querySelector('button')) {
      const btn = document.createElement('button');
      btn.textContent = 'Load more';
      btn.setAttribute('type', 'button');
      this.appendChild(btn);
    }
    const btn = this.querySelector('button')!;
    btn.addEventListener('click', () => {
      void this.#loadNext();
    });
  }

  #setupScrollSpy(): void {
    this.#scrollSpy = new ScrollSpy(this.#scroller, (url: string) => {
      history.replaceState(null, '', url);
    });

    const canonical = this.getAttribute('data-canonical');
    const grid = this.#getGrid();
    if (canonical && grid) {
      const firstCard = grid.firstElementChild;
      if (firstCard) {
        this.#scrollSpy.addBatch(canonical, firstCard);
      }
    }

    this.#scrollSpy.start();
  }

  #setupSentinel(): void {
    if (!this.#sentinel) {
      this.#sentinel = document.createElement('span');
      this.#sentinel.setAttribute('aria-hidden', 'true');
      this.appendChild(this.#sentinel);
    }

    this.#observer = new IntersectionObserver((entries) => {
      const entry = entries[0];
      if (entry?.isIntersecting) {
        void this.#loadNext();
      }
    });

    this.#observer.observe(this.#sentinel);
  }

  #bindLoadPrevious(): void {
    const btn = this.#getLoadPreviousButton();
    if (btn) {
      btn.addEventListener('click', () => {
        void this.#loadPrevious();
      });
    }
  }

  async #loadNext(): Promise<void> {
    const nextUrl = this.getAttribute('data-next');
    if (!nextUrl) {
      return;
    }
    const grid = this.#getGrid();

    this.#observer?.disconnect();

    const result = await loadFragment(nextUrl);

    if (grid && result.cards.length > 0) {
      const countBefore = grid.children.length;
      appendCards(grid, result.cards);
      const firstOfBatch = grid.children[countBefore];

      if (result.canonical && firstOfBatch && this.#scrollSpy) {
        this.#scrollSpy.addBatch(result.canonical, firstOfBatch);
      }
    }

    if (result.next) {
      this.setAttribute('data-next', result.next);
      if (this.#sentinel) {
        this.#observer = new IntersectionObserver((entries) => {
          const entry = entries[0];
          if (entry?.isIntersecting) {
            void this.#loadNext();
          }
        });
        this.#observer.observe(this.#sentinel);
      }
    } else {
      this.removeAttribute('data-next');
      this.remove();
    }
  }

  async #loadPrevious(): Promise<void> {
    // data-prev lives on the element, not on the (sibling) button.
    const prevUrl = this.getAttribute('data-prev');
    if (!prevUrl) {
      return;
    }
    const grid = this.#getGrid();

    const result = await loadFragment(prevUrl);

    if (grid && result.cards.length > 0) {
      prependCardsAnchored(grid, result.cards, this.#scroller);

      const firstOfBatch = grid.firstElementChild;
      if (result.canonical && firstOfBatch && this.#scrollSpy) {
        this.#scrollSpy.addBatch(result.canonical, firstOfBatch);
      }
    }

    if (result.prev) {
      this.setAttribute('data-prev', result.prev);
    } else {
      this.removeAttribute('data-prev');
      this.#getLoadPreviousButton()?.remove();
    }
  }

  override render(): unknown {
    return html`<slot></slot>`;
  }
}

registerBase('mk-infinite-scroll', MkInfiniteScrollElement);
