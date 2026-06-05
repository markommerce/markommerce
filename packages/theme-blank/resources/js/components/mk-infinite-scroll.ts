import { html } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';

export class MkInfiniteScrollElement extends MkElement {
  #observer: IntersectionObserver | null = null;
  #sentinel: HTMLElement | null = null;

  override connectedCallback(): void {
    super.connectedCallback();
    this.#ensureFallbackButton();
    this.#setupSentinel();
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    this.#observer?.disconnect();
    this.#observer = null;
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

  async #loadNext(): Promise<void> {
    const nextUrl = this.getAttribute('data-next');
    if (!nextUrl) {
      return;
    }
    const gridSelector = this.getAttribute('data-grid');
    const grid = gridSelector ? document.querySelector(gridSelector) : null;

    this.#observer?.disconnect();

    const response = await fetch(nextUrl);
    const htmlText = await response.text();

    const template = document.createElement('template');
    template.innerHTML = htmlText;
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

  override render(): unknown {
    return html`<slot></slot>`;
  }
}

registerBase('mk-infinite-scroll', MkInfiniteScrollElement);
