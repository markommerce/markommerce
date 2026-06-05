import { MkElement, registerBase } from '@markommerce/frontend';
import { loadFragment, appendCards, prependCardsAnchored, ScrollSpy } from './pagination-loader';

export class MkLoadMoreElement extends MkElement {
  #scrollSpy: ScrollSpy | null = null;
  #loadMoreBound = false;
  #loadPreviousBound = false;

  override connectedCallback(): void {
    super.connectedCallback();
    this.#initScrollSpy();
    this.#bindLoadMore();
    this.#bindLoadPrevious();
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    this.#scrollSpy?.stop();
  }

  #initScrollSpy(): void {
    if (this.#scrollSpy !== null) {
      return;
    }
    this.#scrollSpy = new ScrollSpy(undefined, (url) => {
      history.replaceState(null, '', url);
    });
    this.#scrollSpy.start();

    const canonical = this.getAttribute('data-canonical');
    const grid = this.#getGrid();
    if (canonical && grid) {
      const firstCard = grid.firstElementChild;
      if (firstCard) {
        this.#scrollSpy.addBatch(canonical, firstCard);
      }
    }
  }

  #bindLoadMore(): void {
    if (this.#loadMoreBound) {
      return;
    }
    const btn = this.querySelector<HTMLButtonElement>('[data-role="load-more"]');
    if (btn) {
      this.#loadMoreBound = true;
      btn.addEventListener('click', () => {
        void this.#loadMore();
      });
    }
  }

  #bindLoadPrevious(): void {
    if (this.#loadPreviousBound) {
      return;
    }
    const btn = this.#getLoadPreviousButton();
    if (btn) {
      this.#loadPreviousBound = true;
      btn.addEventListener('click', () => {
        void this.#loadPrevious();
      });
    }
  }

  #getGrid(): Element | null {
    const gridSelector = this.getAttribute('data-grid');
    return gridSelector ? document.querySelector(gridSelector) : null;
  }

  #getLoadPreviousButton(): HTMLButtonElement | null {
    const grid = this.#getGrid();
    if (!grid) {
      return null;
    }
    return grid.parentElement?.querySelector<HTMLButtonElement>('[data-role="load-previous"]') ?? null;
  }

  async #loadMore(): Promise<void> {
    const nextUrl = this.getAttribute('data-next');
    if (!nextUrl) {
      return;
    }
    const grid = this.#getGrid();
    if (!grid) {
      return;
    }

    const fragment = await loadFragment(nextUrl);

    if (fragment.cards.length > 0) {
      const countBefore = grid.children.length;
      appendCards(grid, fragment.cards);
      const firstAppended = grid.children[countBefore];
      if (firstAppended && fragment.canonical) {
        this.#scrollSpy?.addBatch(fragment.canonical, firstAppended);
      }
    }

    if (fragment.next) {
      this.setAttribute('data-next', fragment.next);
    } else {
      this.removeAttribute('data-next');
      const btn = this.querySelector<HTMLButtonElement>('[data-role="load-more"]');
      btn?.remove();
    }
  }

  async #loadPrevious(): Promise<void> {
    const prevUrl = this.getAttribute('data-prev');
    if (!prevUrl) {
      return;
    }
    const grid = this.#getGrid();
    if (!grid) {
      return;
    }

    const fragment = await loadFragment(prevUrl);

    if (fragment.cards.length > 0) {
      prependCardsAnchored(grid, fragment.cards);
      const firstPrepended = grid.firstElementChild;
      if (firstPrepended && fragment.canonical) {
        this.#scrollSpy?.addBatch(fragment.canonical, firstPrepended);
      }
    }

    if (fragment.prev) {
      this.setAttribute('data-prev', fragment.prev);
    } else {
      this.removeAttribute('data-prev');
      const btn = this.#getLoadPreviousButton();
      btn?.remove();
    }
  }
}

registerBase('mk-load-more', MkLoadMoreElement);
