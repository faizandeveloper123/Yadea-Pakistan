import { useSyncExternalStore } from 'react';

export interface StoredFormElement {
  label: string;
  type: string;
}

export interface StoredForm {
  id: string;
  name: string;
  elements: StoredFormElement[];
}

const STORAGE_KEY = 'evee_crm_forms';

function defaultForms(): StoredForm[] {
  return [
    {
      id: 'form-0',
      name: 'Form 0',
      elements: [
        { label: 'First Name', type: 'text' },
        { label: 'Last Name', type: 'text' },
        { label: 'Phone', type: 'phone' },
        { label: 'Email', type: 'email' },
      ],
    },
    {
      id: 'auto-dealer-contact-us',
      name: 'Auto Dealer Contact Us',
      elements: [
        { label: 'Full Name', type: 'text' },
        { label: 'Phone', type: 'phone' },
        { label: 'Email', type: 'email' },
        { label: 'Preferred Contact Method', type: 'single_dropdown' },
        { label: 'Are you looking for', type: 'single_dropdown' },
        { label: 'Preferred Features (check all that apply)', type: 'multi_dropdown' },
        { label: 'I Consent to Receive SMS Notifications', type: 'checkbox' },
      ],
    },
  ];
}

function load(): StoredForm[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed)) return parsed as StoredForm[];
    }
  } catch {
    /* ignore malformed storage */
  }
  return defaultForms();
}

let forms: StoredForm[] = load();
let snapshot: StoredForm[] | null = null;
const listeners = new Set<() => void>();

function persist(): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(forms));
  } catch {
    /* ignore quota errors */
  }
}

function emit(): void {
  snapshot = null;
  persist();
  listeners.forEach((l) => l());
}

function subscribe(cb: () => void): () => void {
  listeners.add(cb);
  return () => {
    listeners.delete(cb);
  };
}

function getSnapshot(): StoredForm[] {
  if (snapshot === null) snapshot = forms;
  return snapshot;
}

export function getForms(): StoredForm[] {
  return forms;
}

/** Register (or update) a form so it appears as a dropdown in Manage Fields. */
export function upsertForm(form: StoredForm): void {
  const existing = forms.find((f) => f.name === form.name);
  if (existing) {
    forms = forms.map((f) => (f.name === form.name ? { ...f, ...form, id: f.id } : f));
  } else {
    forms = [...forms, form];
  }
  emit();
}

/** Called when a public form is submitted - records the form and its fields. */
export function recordFormSubmission(name: string, elements: { label: string; type: string }[]): void {
  const fields = elements.filter((el) => el.type !== 'button');
  if (!name || fields.length === 0) return;
  upsertForm({
    id: `form-${name}`,
    name,
    elements: fields.map((el) => ({ label: el.label, type: el.type })),
  });
}

/** Hook for components that render the form dropdowns (e.g. Manage Fields). */
export function useForms(): StoredForm[] {
  return useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
}