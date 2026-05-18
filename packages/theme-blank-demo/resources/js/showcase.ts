import { showToast, openModal, openDrawer } from '@markommerce/theme-blank';

document.addEventListener('click', (e: Event) => {
  const target = (e.target as Element).closest('[data-demo]') as HTMLElement | null;
  if (!target) return;

  const demo = target.dataset['demo'];

  if (demo === 'toast') {
    showToast('Demo toast notification!', { variant: 'info' });
  } else if (demo === 'modal') {
    openModal(
      '<p>Are you sure?</p><form method="dialog"><button>Close</button></form>',
      { dismissible: true },
    );
  } else if (demo === 'drawer-right') {
    openDrawer(
      '<p>Mini-cart placeholder</p><form method="dialog"><button>Close</button></form>',
      { placement: 'right', dismissible: true },
    );
  } else if (demo === 'drawer-left') {
    openDrawer(
      '<p>Mini-cart placeholder</p><form method="dialog"><button>Close</button></form>',
      { placement: 'left', dismissible: true },
    );
  }
});
