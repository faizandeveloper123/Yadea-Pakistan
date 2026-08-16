/**
 * Lightweight hash-based router.
 *
 * Routes are kept in the URL hash so that refreshing the browser keeps the
 * user on the same page (no more bouncing back to the Contacts list).
 *
 * Supported routes:
 *   #/contacts        -> CRM Contacts dashboard
 *   #/contact/:id     -> single Lead/Contact detail page
 *   #/sites           -> Sites / Forms dashboard (standalone page)
 *   #/settings        -> Settings page (standalone page, own sidebar)
 *   #/dashboard       -> Dealer / Franchise dashboard (owner vs user views)
 */

export type Route =
  | { name: 'contacts' }
  | { name: 'contact'; id: number }
  | { name: 'sites' }
  | { name: 'settings' }
  | { name: 'smart-lists' }
  | { name: 'dashboard' }
  | { name: 'automation' }
  | { name: 'form'; data: string };

export const DEFAULT_ROUTE: Route = { name: 'dashboard' };

export function parseHash(): Route {
  const raw = window.location.hash.replace(/^#/, '');
  const parts = raw.split('/').filter((p) => p !== '');
  if (parts.length === 0) return DEFAULT_ROUTE;

  const [resource, idPart] = parts;

  if (resource === 'contact') {
    const id = Number(idPart);
    if (Number.isInteger(id) && id > 0) return { name: 'contact', id };
    return DEFAULT_ROUTE;
  }

  switch (resource) {
    case 'contacts':
    case 'leads':
      return { name: 'contacts' };
    case 'sites':
      return { name: 'sites' };
    case 'settings':
      return { name: 'settings' };
    case 'smart-lists':
      return { name: 'smart-lists' };
    case 'dashboard':
      return { name: 'dashboard' };
    case 'automation':
      return { name: 'automation' };
    case 'form':
      if (idPart) return { name: 'form', data: idPart };
      return DEFAULT_ROUTE;
    default:
      return DEFAULT_ROUTE;
  }
}

export function routeToHash(route: Route): string {
  switch (route.name) {
    case 'contact':
      return `#/contact/${route.id}`;
    case 'sites':
      return '#/sites';
    case 'settings':
      return '#/settings';
    case 'smart-lists':
      return '#/smart-lists';
    case 'dashboard':
      return '#/dashboard';
    case 'automation':
      return '#/automation';
    case 'form':
      return `#/form/${route.data}`;
    case 'contacts':
    default:
      return '#/contacts';
  }
}

export function navigate(route: Route): void {
  const hash = routeToHash(route);
  if (window.location.hash === hash) return;
  window.location.hash = hash;
}

/** Subscribe to hash changes; returns an unsubscribe function. */
export function onHashChange(listener: () => void): () => void {
  window.addEventListener('hashchange', listener);
  return () => window.removeEventListener('hashchange', listener);
}