import { MkElement, registerBase } from '@markommerce/frontend';

export class MkLoadMoreElement extends MkElement {
  override connectedCallback(): void {
    super.connectedCallback();
    const btn = this.querySelector('button');
    if (btn) {
      btn.addEventListener('click', () => {
        void this.#loadMore();
      });
    }
  }

  async #loadMore(): Promise<void> {
    const nextUrl = this.getAttribute('data-next');
    if (!nextUrl) {
      return;
    }
    const gridSelector = this.getAttribute('data-grid');
    const grid = gridSelector ? document.querySelector(gridSelector) : null;

    const response = await fetch(nextUrl);
    const html = await response.text();

    const template = document.createElement('template');
    template.innerHTML = html;
    const fragment = template.content.querySelector('.catalog-product-grid-fragment');
    if (!fragment) {
      return;
    }

    if (grid) {
      const cards = Array.from(fragment.childNodes);
      for (const card of cards) {
        grid.appendChild(card.cloneNode(true));
      }
    }

    const nextNext = (fragment as HTMLElement).getAttribute('data-next');
    if (nextNext) {
      this.setAttribute('data-next', nextNext);
      history.pushState(null, '', nextNext);
    } else {
      this.removeAttribute('data-next');
      this.remove();
    }
  }
}

registerBase('mk-load-more', MkLoadMoreElement);
