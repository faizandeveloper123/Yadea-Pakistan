import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';
import {
  FaFileInvoice,
  FaFilePdf,
  FaFloppyDisk,
  FaMagnifyingGlass,
  FaPlus,
  FaPrint,
  FaRegTrashCan,
  FaRotateRight,
  FaCircleInfo,
  FaPenToSquare,
  FaChartLine,
  FaMotorcycle,
} from 'react-icons/fa6';
import { api, type ApiInvoice, type InvoiceInput } from '../api';
import { useAuth } from '../auth';
import YadeaLogo from './YadeaLogo';

/* ------------------------------ helpers ------------------------------ */

function parseNum(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0;
  const cleaned = String(value).replace(/[^0-9.-]+/g, '');
  const parsed = parseFloat(cleaned);
  return Number.isNaN(parsed) ? 0 : parsed;
}

function formatMoney(amount: number): string {
  if (!Number.isFinite(amount)) return '0.00';
  return amount.toLocaleString('en-PK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function todayDMY(): string {
  const d = new Date();
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

/* --------------------- dashboard history filters --------------------- */

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export type InvoiceRangeKey =
  | 'all'
  | 'today'
  | 'tomorrow'
  | 'yesterday'
  | 'week'
  | 'month'
  | 'year'
  | 'custom';

export const INVOICE_RANGES: { key: InvoiceRangeKey; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'today', label: 'Today' },
  { key: 'tomorrow', label: 'Tomorrow' },
  { key: 'yesterday', label: 'Previous Day' },
  { key: 'week', label: 'This Week' },
  { key: 'month', label: 'This Month' },
  { key: 'year', label: 'This Year' },
  { key: 'custom', label: 'Custom' },
];

/** Parse the slip's free-text "DD/MM/YYYY" date into a Date (or null). */
function parseDMY(value: string | null | undefined): Date | null {
  if (!value) return null;
  const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(value.trim());
  if (!m) return null;
  const d = new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]));
  return Number.isNaN(d.getTime()) ? null : d;
}

function startOfDay(d: Date): Date {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

function endOfDay(d: Date): Date {
  const x = new Date(d);
  x.setHours(23, 59, 59, 999);
  return x;
}

/** Resolve a preset (or custom range) into an inclusive [start, end] window. */
function getRange(key: InvoiceRangeKey, from: string, to: string): [Date | null, Date | null] {
  const now = new Date();
  switch (key) {
    case 'today':
      return [startOfDay(now), endOfDay(now)];
    case 'tomorrow': {
      const t = new Date(now);
      t.setDate(t.getDate() + 1);
      return [startOfDay(t), endOfDay(t)];
    }
    case 'yesterday': {
      const y = new Date(now);
      y.setDate(y.getDate() - 1);
      return [startOfDay(y), endOfDay(y)];
    }
    case 'week': {
      // Monday-start current week through today.
      const s = startOfDay(now);
      s.setDate(s.getDate() - ((s.getDay() + 6) % 7));
      return [s, endOfDay(now)];
    }
    case 'month':
      return [new Date(now.getFullYear(), now.getMonth(), 1), endOfDay(now)];
    case 'year':
      return [new Date(now.getFullYear(), 0, 1), endOfDay(now)];
    case 'custom': {
      const f = from ? new Date(from) : null;
      const t = to ? new Date(to) : null;
      const validF = f && !Number.isNaN(f.getTime()) ? f : null;
      const validT = t && !Number.isNaN(t.getTime()) ? endOfDay(t) : null;
      return [validF, validT];
    }
    default:
      return [null, null];
  }
}

interface InvoiceFormState {
  invoiceNo: string;
  dated: string;
  strn: string;
  ms: string;
  qty: string;
  motorcycle: string;
  year: string;
  color: string;
  engine: string;
  chassis: string;
  valueExcl: string;
  taxRate: string;
}

/** Defaults carried over from the printed Yadea slip (fixed shop details). */
const EMPTY_FORM: InvoiceFormState = {
  invoiceNo: '',
  dated: '',
  strn: '3277876272668',
  ms: '',
  qty: '1',
  motorcycle: '',
  year: '',
  color: '',
  engine: '',
  chassis: '',
  valueExcl: '',
  taxRate: '18',
};

function emptyFormWithDefaults(): InvoiceFormState {
  return { ...EMPTY_FORM, dated: todayDMY() };
}

/* --------------------- lazy html2pdf (CDN) loader -------------------- */

const HTML2PDF_CDN =
  'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';

type Html2PdfApi = {
  (): {
    set: (opt: Record<string, unknown>) => {
      from: (el: HTMLElement) => { save: () => Promise<void> };
    };
  };
};

function getHtml2Pdf(): Html2PdfApi | null {
  const w = window as unknown as { html2pdf?: Html2PdfApi };
  return w.html2pdf ?? null;
}

function loadHtml2Pdf(): Promise<Html2PdfApi> {
  return new Promise((resolve, reject) => {
    const existing = getHtml2Pdf();
    if (existing) {
      resolve(existing);
      return;
    }
    const script = document.createElement('script');
    script.src = HTML2PDF_CDN;
    script.async = true;
    script.onload = () => {
      const lib = getHtml2Pdf();
      if (lib) resolve(lib);
      else reject(new Error('PDF library failed to initialise'));
    };
    script.onerror = () =>
      reject(new Error('Could not load the PDF library (check your internet connection)'));
    document.head.appendChild(script);
  });
}

/* ------------------------------- icons ------------------------------ */

const DESC_ROWS: { key: keyof InvoiceFormState; label: string; placeholder: string }[] = [
  { key: 'motorcycle', label: 'Motorcycle', placeholder: 'e.g. Yadea T9 Electric Scooter' },
  { key: 'year', label: 'M/Year', placeholder: '2026' },
  { key: 'color', label: 'Colur', placeholder: 'Grey / Red' },
  { key: 'engine', label: 'Engine#', placeholder: 'Engine No.' },
  { key: 'chassis', label: 'Chasis', placeholder: 'Chassis No.' },
];

/* ================================ page ============================== */

interface InvoicesPageProps {
  onNotify: (msg: string) => void;
}

export default function InvoicesPage({ onNotify }: InvoicesPageProps) {
  const { user, hasActionPermission } = useAuth();
  const canEdit = hasActionPermission('invoices', 'Invoices', 'edit');
  const canDelete = hasActionPermission('invoices', 'Invoices', 'delete');
  const canExport = hasActionPermission('invoices', 'Invoices', 'export');

  const [form, setForm] = useState<InvoiceFormState>(emptyFormWithDefaults);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [pdfBusy, setPdfBusy] = useState(false);

  const [invoices, setInvoices] = useState<ApiInvoice[]>([]);
  const [loadingList, setLoadingList] = useState(true);

  // Dashboard view + history filters.
  const [view, setView] = useState<'editor' | 'dashboard'>('editor');
  const [rangeKey, setRangeKey] = useState<InvoiceRangeKey>('all');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const [dashSearch, setDashSearch] = useState('');

  const docRef = useRef<HTMLDivElement | null>(null);

  /* While the editor is mounted every print (button or Ctrl+P) outputs only
     the invoice sheet - the CSS below hides everything else. */
  useEffect(() => {
    if (view !== 'editor') {
      document.body.classList.remove('inv-printing');
      return undefined;
    }
    document.body.classList.add('inv-printing');
    return () => document.body.classList.remove('inv-printing');
  }, [view]);

  /* Suggested next sequential number for a fresh invoice. */
  useEffect(() => {
    let active = true;
    api
      .nextInvoiceNumber()
      .then((res) => {
        if (!active) return;
        setForm((prev) =>
          prev.invoiceNo === '' ? { ...prev, invoiceNo: res.data.invoice_no } : prev
        );
      })
      .catch(() => {
        /* offline: leave the field empty for manual entry */
      });
    return () => {
      active = false;
    };
  }, []);

  /* Invoice listing for the dashboard (search/filtering happens client-side). */
  const loadInvoices = useCallback(async () => {
    setLoadingList(true);
    try {
      const params: { created_by?: number } = {};
      if (user && user.user_type !== 'Admin') params.created_by = user.id;
      const res = await api.listInvoices(params);
      setInvoices(res.data);
    } catch (err) {
      onNotify(`Failed to load invoices: ${(err as Error).message}`);
    } finally {
      setLoadingList(false);
    }
  }, [user, onNotify]);

  // Fetch whenever the dashboard view opens (and on user change).
  useEffect(() => {
    if (view === 'dashboard') void loadInvoices();
  }, [view, loadInvoices]);

  /* ------------------------- live calculations ------------------------ */

  const qtyNum = parseNum(form.qty) || 1;
  const valueExcl = parseNum(form.valueExcl);
  const taxRate = parseNum(form.taxRate);
  const totalExcl = valueExcl * qtyNum;
  const taxPayable = totalExcl * (taxRate / 100);
  const totalIncl = totalExcl + taxPayable;

  /* ----------------------- dashboard derivations ---------------------- */

  const [rangeStart, rangeEnd] = useMemo(
    () => getRange(rangeKey, customFrom, customTo),
    [rangeKey, customFrom, customTo]
  );

  const filteredInvoices = useMemo(() => {
    const q = dashSearch.trim().toLowerCase();
    return invoices
      .filter((inv) => {
        const d = parseDMY(inv.dated);
        if (rangeStart && (!d || d < rangeStart)) return false;
        if (rangeEnd && (!d || d > rangeEnd)) return false;
        if (q) {
          const hay =
            `${inv.invoice_no} ${inv.customer_name} ${inv.motorcycle} ${inv.engine_no} ${inv.chassis_no}`.toLowerCase();
          if (!hay.includes(q)) return false;
        }
        return true;
      })
      .sort((a, b) => {
        const da = parseDMY(a.dated)?.getTime() ?? 0;
        const db = parseDMY(b.dated)?.getTime() ?? 0;
        return db - da || b.id - a.id;
      });
  }, [invoices, rangeStart, rangeEnd, dashSearch]);

  const dashCount = filteredInvoices.length;
  const dashRevenue = filteredInvoices.reduce((s, i) => s + (Number(i.value_incl) || 0), 0);
  const dashUnits = filteredInvoices.reduce((s, i) => s + (Number(i.qty) || 0), 0);

  /** Model-wise breakdown of the currently filtered history. */
  const modelSummary = useMemo(() => {
    const map = new Map<string, { model: string; units: number; revenue: number }>();
    filteredInvoices.forEach((inv) => {
      const key = (inv.motorcycle || '').trim();
      if (!key) return;
      const cur = map.get(key.toLowerCase()) ?? { model: key, units: 0, revenue: 0 };
      cur.units += Number(inv.qty) || 0;
      cur.revenue += Number(inv.value_incl) || 0;
      map.set(key.toLowerCase(), cur);
    });
    return [...map.values()].sort((a, b) => b.revenue - a.revenue);
  }, [filteredInvoices]);

  const openInvoiceInEditor = (inv: ApiInvoice) => {
    handleLoad(inv);
    setView('editor');
  };

  const setField =
    (key: keyof InvoiceFormState) =>
    (e: ChangeEvent<HTMLInputElement>) =>
      setForm((prev) => ({ ...prev, [key]: e.target.value }));

  /* ----------------------------- actions ------------------------------ */

  const handleNew = () => {
    setEditingId(null);
    setView('editor');
    setForm(emptyFormWithDefaults());
    api
      .nextInvoiceNumber()
      .then((res) => setForm((prev) => ({ ...prev, invoiceNo: res.data.invoice_no })))
      .catch(() => undefined);
  };

  const buildPayload = (): InvoiceInput => ({
    invoice_no: form.invoiceNo.trim(),
    dated: form.dated.trim(),
    strn: form.strn.trim(),
    customer_name: form.ms.trim(),
    qty: Math.max(1, Math.round(qtyNum)),
    motorcycle: form.motorcycle.trim(),
    model_year: form.year.trim(),
    colour: form.color.trim(),
    engine_no: form.engine.trim(),
    chassis_no: form.chassis.trim(),
    value_excl: valueExcl,
    tax_rate: taxRate,
    tax_payable: Math.round(taxPayable * 100) / 100,
    value_incl: Math.round(totalIncl * 100) / 100,
    created_by: user?.id ?? null,
  });

  const handleSave = async () => {
    if (!canEdit || saving) return;
    if (!form.invoiceNo.trim()) {
      onNotify('Invoice number is required');
      return;
    }
    if (!form.ms.trim()) {
      onNotify('Customer name (M/S) is required');
      return;
    }

    setSaving(true);
    try {
      const payload = buildPayload();
      if (editingId !== null) {
        await api.updateInvoice(editingId, payload);
        onNotify(`Invoice "${payload.invoice_no}" updated`);
      } else {
        const res = await api.createInvoice(payload);
        setEditingId(res.data.id);
        onNotify(`Invoice "${payload.invoice_no}" saved to database`);
      }
      await loadInvoices();
    } catch (err) {
      onNotify(`Save failed: ${(err as Error).message}`);
    } finally {
      setSaving(false);
    }
  };

  const handleLoad = (inv: ApiInvoice) => {
    setEditingId(inv.id);
    setForm({
      invoiceNo: inv.invoice_no,
      dated: inv.dated ?? '',
      strn: inv.strn ?? '',
      ms: inv.customer_name ?? '',
      qty: String(inv.qty ?? 1),
      motorcycle: inv.motorcycle ?? '',
      year: inv.model_year ?? '',
      color: inv.colour ?? '',
      engine: inv.engine_no ?? '',
      chassis: inv.chassis_no ?? '',
      valueExcl: String(inv.value_excl ?? 0),
      taxRate: String(inv.tax_rate ?? 18),
    });
    onNotify(`Loaded invoice "${inv.invoice_no}"`);
  };

  const handleDelete = async (inv: ApiInvoice) => {
    if (!canDelete) return;
    if (!window.confirm(`Delete invoice "${inv.invoice_no}"? This cannot be undone.`)) return;
    try {
      await api.deleteInvoice(inv.id);
      if (editingId === inv.id) setEditingId(null);
      onNotify(`Invoice "${inv.invoice_no}" deleted`);
      await loadInvoices();
    } catch (err) {
      onNotify(`Delete failed: ${(err as Error).message}`);
    }
  };

  const handlePrint = () => window.print();

  const handleDownloadPdf = async () => {
    if (!docRef.current || pdfBusy) return;
    setPdfBusy(true);
    try {
      const html2pdf = await loadHtml2Pdf();
      await html2pdf()
        .set({
          margin: [0.15, 0.15, 0.15, 0.15],
          filename: `Yadea_Sales_Tax_Invoice_${form.invoiceNo.trim() || 'draft'}.pdf`,
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, logging: false, scrollX: 0, scrollY: 0 },
          jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
        })
        .from(docRef.current)
        .save();
    } catch (err) {
      onNotify(`${(err as Error).message} - opening the print dialog instead`);
      window.print();
    } finally {
      setPdfBusy(false);
    }
  };

  /* ------------------------------- view ------------------------------- */

  return (
    <>
      {/* Scoped styles: editable-field look + print isolation */}
      <style>{`
        .inv-dotted { border-bottom: 1.5px dotted #94a3b8; background: transparent; transition: border-color .15s ease; }
        .inv-dotted:focus { border-bottom: 1.5px solid #EB5F1B; outline: none; }
        .inv-underlined { border-bottom: 1px solid #1e293b; background: transparent; }
        .inv-underlined:focus { border-bottom: 2px solid #EB5F1B; outline: none; }
        .inv-bare:focus { outline: none; background-color: rgba(254, 243, 199, 0.45); }
        .preview-x::-webkit-scrollbar, .inv-scroll-y::-webkit-scrollbar { height: 6px; width: 6px; }
        .preview-x::-webkit-scrollbar-thumb, .inv-scroll-y::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .preview-x::-webkit-scrollbar-track, .inv-scroll-y::-webkit-scrollbar-track { background: transparent; }
        .inv-table-wrap { border: 2px solid #111827; border-radius: 14px; overflow: hidden; background: #fff; }
        .inv-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .inv-table th { border-right: 1.5px solid #111827; border-bottom: 2px solid #111827; font-size: 11px; font-weight: 800; color: #0f172a; padding: 6px 4px; text-align: center; background: #fff; }
        .inv-table td { border-right: 1.5px solid #111827; border-bottom: 1.5px solid #111827; padding: 6px 4px; vertical-align: top; }
        .inv-table th:last-child, .inv-table td:last-child { border-right: none; }
        .inv-table tr.inv-total-row td { border-bottom: none; border-top: 2px solid #111827; background: #fff; }
        @media print {
          body.inv-printing * { visibility: hidden !important; }
          body.inv-printing .inv-page, body.inv-printing .inv-page * { visibility: visible !important; }
          body.inv-printing .inv-scroll-host { overflow: visible !important; padding: 0 !important; }
          body.inv-printing .inv-page { position: absolute; left: 0; top: 0; width: 100% !important; min-width: 0 !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; }
          body.inv-printing input { border: none !important; box-shadow: none !important; background: transparent !important; }
        }
      `}</style>

      <div className="h-full overflow-y-auto bg-slate-100 min-w-0">
        <div className="max-w-[1440px] mx-auto px-3 md:px-6 pb-8">
          {/* Page heading + sticky action bar */}
          <div className="inv-no-print sticky top-0 z-30 -mx-3 md:-mx-6 px-3 md:px-6 pt-4 pb-3 mb-5 bg-slate-100/85 backdrop-blur-md border-b border-slate-200/70 flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="w-11 h-11 rounded-xl bg-yadea-black flex items-center justify-center shadow-md shadow-slate-900/20 ring-1 ring-black/10">
                <YadeaLogo wordmark={false} className="h-7 w-auto" />
              </div>
              <div>
                <h1 className="text-lg font-extrabold text-slate-900 leading-tight">
                  Sales Tax Invoice
                </h1>
                <p className="text-xs text-slate-500">
                  Create, save and export official Yadea Hussain Motors invoices
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2 flex-wrap">
              {editingId !== null && (
                <span className="inline-flex items-center gap-1.5 bg-yadea-orange/10 text-yadea-dark border border-yadea-orange/30 rounded-lg px-2.5 py-2 text-[11px] font-bold">
                  <FaPenToSquare className="text-[10px]" />
                  Editing #{form.invoiceNo || editingId}
                </span>
              )}
              <button
                type="button"
                onClick={() => setView((v) => (v === 'editor' ? 'dashboard' : 'editor'))}
                className={`inline-flex items-center gap-1.5 h-9 px-3.5 text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] ${
                  view === 'dashboard'
                    ? 'bg-slate-900 hover:bg-slate-800 text-white'
                    : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 hover:border-slate-400'
                }`}
              >
                <FaChartLine className="text-yadea-orange" />
                {view === 'editor' ? 'Invoice Dashboard' : 'Back to Editor'}
              </button>
              {view === 'editor' && (
                <>
                  <button
                    type="button"
                    onClick={handleNew}
                    className="inline-flex items-center gap-1.5 h-9 px-3.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-lg border border-slate-300 hover:border-slate-400 shadow-sm transition active:scale-[0.98]"
                  >
                    <FaPlus className="text-[10px]" /> New
                  </button>
                  <button
                    type="button"
                    onClick={handleSave}
                    disabled={!canEdit || saving}
                    title={canEdit ? 'Save invoice to database' : 'You do not have edit permission'}
                    className="inline-flex items-center gap-1.5 h-9 px-4 bg-yadea-orange hover:bg-yadea-dark text-white text-xs font-bold rounded-lg shadow-sm shadow-yadea-orange/40 transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
                  >
                    <FaFloppyDisk className={saving ? 'animate-pulse' : ''} />
                    {saving ? 'Saving...' : editingId !== null ? 'Update' : 'Save'}
                  </button>
                  <button
                    type="button"
                    onClick={handlePrint}
                    className="inline-flex items-center gap-1.5 h-9 px-3.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-lg border border-slate-300 hover:border-slate-400 shadow-sm transition active:scale-[0.98]"
                  >
                    <FaPrint /> Print
                  </button>
                  <button
                    type="button"
                    onClick={() => void handleDownloadPdf()}
                    disabled={!canExport || pdfBusy}
                    title={canExport ? 'Download as PDF' : 'You do not have export permission'}
                    className="inline-flex items-center gap-1.5 h-9 px-4 bg-yadea-black hover:bg-slate-800 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <FaFilePdf className={`text-yadea-orange ${pdfBusy ? 'animate-pulse' : ''}`} />
                    {pdfBusy ? 'Preparing...' : 'Download PDF'}
                  </button>
                </>
              )}
            </div>
          </div>

          {view === 'dashboard' ? (
            /* --------------------- Invoice Dashboard --------------------- */
            <div className="space-y-4">
              {/* Summary stat cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-yadea-orange/10 text-yadea-dark flex items-center justify-center shrink-0">
                    <FaFileInvoice />
                  </div>
                  <div className="min-w-0">
                    <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Invoices</div>
                    <div className="text-xl font-black text-slate-900">{loadingList ? '...' : dashCount}</div>
                  </div>
                </div>
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-yadea-orange/10 text-yadea-dark flex items-center justify-center shrink-0">
                    <FaChartLine />
                  </div>
                  <div className="min-w-0">
                    <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Sales</div>
                    <div className="text-xl font-black text-slate-900 truncate">
                      Rs {loadingList ? '...' : formatMoney(dashRevenue)}
                    </div>
                  </div>
                </div>
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-yadea-orange/10 text-yadea-dark flex items-center justify-center shrink-0">
                    <FaMotorcycle />
                  </div>
                  <div className="min-w-0">
                    <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Bikes Sold</div>
                    <div className="text-xl font-black text-slate-900">{loadingList ? '...' : dashUnits}</div>
                  </div>
                </div>
              </div>

              {/* Filters bar */}
              <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-3 flex flex-wrap items-center gap-2">
                {INVOICE_RANGES.map((r) => (
                  <button
                    key={r.key}
                    type="button"
                    onClick={() => setRangeKey(r.key)}
                    className={`h-7 px-3 rounded-full text-[11px] font-bold transition active:scale-[0.97] ${
                      rangeKey === r.key
                        ? 'bg-yadea-orange text-white shadow-sm shadow-yadea-orange/40'
                        : 'bg-slate-50 text-slate-500 border border-slate-200 hover:border-yadea-orange/50 hover:text-yadea-dark'
                    }`}
                  >
                    {r.label}
                  </button>
                ))}
                {rangeKey === 'custom' && (
                  <span className="inline-flex flex-wrap items-center gap-1.5 ml-1">
                    <input
                      type="datetime-local"
                      value={customFrom}
                      onChange={(e) => setCustomFrom(e.target.value)}
                      className="h-7 text-[11px] border border-slate-300 rounded-md px-1.5 text-slate-700 focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/30"
                      aria-label="From date and time"
                    />
                    <span className="text-[11px] text-slate-400 font-semibold">to</span>
                    <input
                      type="datetime-local"
                      value={customTo}
                      onChange={(e) => setCustomTo(e.target.value)}
                      className="h-7 text-[11px] border border-slate-300 rounded-md px-1.5 text-slate-700 focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/30"
                      aria-label="To date and time"
                    />
                    {(customFrom || customTo) && (
                      <button
                        type="button"
                        onClick={() => {
                          setCustomFrom('');
                          setCustomTo('');
                        }}
                        className="h-7 px-2 rounded-md text-[10px] font-bold text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                      >
                        Clear
                      </button>
                    )}
                  </span>
                )}
                <div className="relative ml-auto">
                  <FaMagnifyingGlass className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[11px]" />
                  <input
                    type="text"
                    placeholder="Search customer, model, #no..."
                    value={dashSearch}
                    onChange={(e) => setDashSearch(e.target.value)}
                    className="h-7 w-full sm:w-56 bg-slate-50 border border-slate-200 rounded-full pl-8 pr-3 text-[11px] focus:outline-none focus:border-yadea-orange focus:ring-1 focus:ring-yadea-orange/40"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => void loadInvoices()}
                  className="w-7 h-7 rounded-full bg-slate-50 border border-slate-200 text-slate-400 hover:text-yadea-orange hover:border-yadea-orange/50 transition flex items-center justify-center shrink-0"
                  aria-label="Refresh history"
                  title="Refresh"
                >
                  <FaRotateRight className="text-[11px]" />
                </button>
              </div>

              {/* Model-wise sales summary */}
              {!loadingList && modelSummary.length > 0 && (
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                  <h3 className="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2.5">
                    Model-wise Sales
                  </h3>
                  <div className="flex flex-wrap gap-2">
                    {modelSummary.map((m) => (
                      <span
                        key={m.model}
                        className="inline-flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-[11px]"
                      >
                        <b className="text-slate-800">{m.model}</b>
                        <span className="text-slate-300">|</span>
                        <span className="text-slate-500">
                          {m.units} unit{m.units === 1 ? '' : 's'}
                        </span>
                        <span className="font-extrabold text-yadea-dark">Rs {formatMoney(m.revenue)}</span>
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {/* Invoice history table */}
              <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                  <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                    Invoice History
                    <span className="text-[10px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded font-bold">
                      {loadingList ? '...' : dashCount}
                    </span>
                  </h3>
                  <span className="text-xs font-extrabold text-yadea-dark whitespace-nowrap">
                    Rs {formatMoney(dashRevenue)}
                  </span>
                </div>
                <div className="overflow-x-auto inv-scroll-y">
                  <table className="w-full text-xs min-w-[760px]">
                    <thead>
                      <tr className="text-left text-[10px] uppercase tracking-wider text-slate-400 bg-slate-50/70 border-b border-slate-200">
                        <th className="py-2.5 pl-4 pr-3 font-extrabold">Date &amp; Day</th>
                        <th className="py-2.5 pr-3 font-extrabold">Invoice#</th>
                        <th className="py-2.5 pr-3 font-extrabold">Customer</th>
                        <th className="py-2.5 pr-3 font-extrabold">Bike Model</th>
                        <th className="py-2.5 pr-3 font-extrabold text-center">Qty</th>
                        <th className="py-2.5 pr-3 font-extrabold text-right">Price (Incl. Tax)</th>
                        <th className="py-2.5 pr-4" />
                      </tr>
                    </thead>
                    <tbody>
                      {loadingList ? (
                        <tr>
                          <td colSpan={7} className="text-center text-slate-400 py-8">
                            Loading invoices...
                          </td>
                        </tr>
                      ) : dashCount === 0 ? (
                        <tr>
                          <td colSpan={7} className="text-center py-10">
                            <FaFileInvoice className="text-3xl text-slate-200 mx-auto mb-2" />
                            <p className="text-slate-400 text-[11px]">
                              No invoices found for this filter.
                            </p>
                          </td>
                        </tr>
                      ) : (
                        filteredInvoices.map((inv) => {
                          const d = parseDMY(inv.dated);
                          return (
                            <tr
                              key={inv.id}
                              onClick={() => openInvoiceInEditor(inv)}
                              className={`border-b border-slate-50 last:border-0 cursor-pointer transition hover:bg-yadea-orange/[0.04] ${
                                editingId === inv.id ? 'bg-yadea-orange/[0.06]' : ''
                              }`}
                              title="Open in editor"
                            >
                              <td className="py-2.5 pl-4 pr-3">
                                <div className="font-bold text-slate-800 whitespace-nowrap">{inv.dated || '-'}</div>
                                <div className="text-[10px] text-slate-400">
                                  {d ? DAY_NAMES[d.getDay()] : 'Unknown day'}
                                </div>
                              </td>
                              <td className="py-2.5 pr-3 font-mono font-extrabold text-green-800 whitespace-nowrap">
                                #{inv.invoice_no}
                              </td>
                              <td className="py-2.5 pr-3 font-bold text-slate-800 max-w-[190px] truncate">
                                {inv.customer_name || 'Unnamed customer'}
                              </td>
                              <td className="py-2.5 pr-3 text-slate-600 max-w-[190px] truncate">
                                {inv.motorcycle || '-'}
                              </td>
                              <td className="py-2.5 pr-3 text-center font-semibold text-slate-700">{inv.qty}</td>
                              <td className="py-2.5 pr-3 text-right font-extrabold text-yadea-dark whitespace-nowrap">
                                Rs {formatMoney(Number(inv.value_incl) || 0)}
                              </td>
                              <td
                                className="py-2.5 pr-4 text-right whitespace-nowrap"
                                onClick={(e) => e.stopPropagation()}
                              >
                                {canDelete && (
                                  <button
                                    type="button"
                                    onClick={() => void handleDelete(inv)}
                                    className="text-slate-300 hover:text-red-600 transition p-1.5 rounded-md hover:bg-red-50"
                                    aria-label={`Delete invoice ${inv.invoice_no}`}
                                    title="Delete"
                                  >
                                    <FaRegTrashCan className="text-xs" />
                                  </button>
                                )}
                              </td>
                            </tr>
                          );
                        })
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          ) : (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {/* ---------------------- Entry panel ---------------------- */}
            <div className="lg:col-span-4 space-y-4">
              <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <h2 className="text-sm font-bold text-slate-800 mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                  <span className="flex items-center gap-2">
                    <FaFileInvoice className="text-yadea-orange" /> Invoice Entry
                  </span>
                  <span className="text-[10px] bg-yadea-orange/10 text-yadea-dark border border-yadea-orange/25 px-2 py-0.5 rounded font-bold">
                    Live Preview
                  </span>
                </h2>

                <div className="space-y-3 text-xs">
                  <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                      <span className="block font-semibold text-slate-700 mb-1">Invoice No</span>
                      <input
                        type="text"
                        value={form.invoiceNo}
                        onChange={setField('invoiceNo')}
                        className="w-full p-2 border border-slate-300 rounded font-mono font-bold text-slate-800 focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                      />
                    </label>
                    <label className="block">
                      <span className="block font-semibold text-slate-700 mb-1">Dated</span>
                      <input
                        type="text"
                        placeholder="DD/MM/YYYY"
                        value={form.dated}
                        onChange={setField('dated')}
                        className="w-full p-2 border border-slate-300 rounded focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                      />
                    </label>
                  </div>

                  <label className="block">
                    <span className="block font-semibold text-slate-700 mb-1">STRN Number</span>
                    <input
                      type="text"
                      value={form.strn}
                      onChange={setField('strn')}
                      className="w-full p-2 border border-slate-300 rounded font-mono focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                    />
                  </label>

                  <label className="block">
                    <span className="block font-semibold text-slate-700 mb-1">Customer / M/S</span>
                    <input
                      type="text"
                      placeholder="Customer or company name"
                      value={form.ms}
                      onChange={setField('ms')}
                      className="w-full p-2 border border-slate-300 rounded font-semibold focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                    />
                  </label>

                  <div className="pt-2 border-t border-slate-100">
                    <span className="font-bold text-slate-900 block mb-2 uppercase tracking-wide text-[11px]">
                      Vehicle Specifications
                    </span>
                    <div className="grid grid-cols-2 gap-2">
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Quantity (Qty)</span>
                        <input
                          type="number"
                          min={1}
                          value={form.qty}
                          onChange={setField('qty')}
                          className="w-full p-2 border border-slate-300 rounded font-bold focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Motorcycle</span>
                        <input
                          type="text"
                          placeholder="Yadea T9 Scooter"
                          value={form.motorcycle}
                          onChange={setField('motorcycle')}
                          className="w-full p-2 border border-slate-300 rounded focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                    </div>
                    <div className="grid grid-cols-2 gap-2 mt-2">
                      <label className="block">
                        <span className="block text-slate-600 mb-1">M/Year</span>
                        <input
                          type="text"
                          placeholder="2026"
                          value={form.year}
                          onChange={setField('year')}
                          className="w-full p-2 border border-slate-300 rounded focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Colour</span>
                        <input
                          type="text"
                          placeholder="Metallic Grey"
                          value={form.color}
                          onChange={setField('color')}
                          className="w-full p-2 border border-slate-300 rounded focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                    </div>
                    <div className="grid grid-cols-2 gap-2 mt-2">
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Engine#</span>
                        <input
                          type="text"
                          value={form.engine}
                          onChange={setField('engine')}
                          className="w-full p-2 border border-slate-300 rounded font-mono text-[11px] focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Chasis</span>
                        <input
                          type="text"
                          value={form.chassis}
                          onChange={setField('chassis')}
                          className="w-full p-2 border border-slate-300 rounded font-mono text-[11px] focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                    </div>
                  </div>

                  <div className="pt-2 border-t border-slate-100">
                    <span className="font-bold text-slate-900 block mb-2 uppercase tracking-wide text-[11px]">
                      Sales Tax Calculation
                    </span>
                    <label className="block">
                      <span className="block text-slate-600 mb-1 font-semibold">
                        Value Excluding Sales Tax (PKR)
                      </span>
                      <input
                        type="number"
                        step="1"
                        placeholder="e.g. 245000"
                        value={form.valueExcl}
                        onChange={setField('valueExcl')}
                        className="w-full p-2 border border-slate-300 rounded font-bold text-sm text-slate-800 focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                      />
                    </label>
                    <div className="grid grid-cols-2 gap-2 mt-2">
                      <label className="block">
                        <span className="block text-slate-600 mb-1">Rate Of Sales Tax (%)</span>
                        <input
                          type="number"
                          step="0.5"
                          value={form.taxRate}
                          onChange={setField('taxRate')}
                          className="w-full p-2 border border-slate-300 rounded font-semibold focus:border-yadea-orange focus:outline-none focus:ring-1 focus:ring-yadea-orange/40"
                        />
                      </label>
                      <div>
                        <span className="block text-slate-600 mb-1">Sales Tax Payable</span>
                        <div className="w-full p-2 bg-slate-50 border border-slate-200 rounded font-semibold text-slate-700 text-right">
                          {formatMoney(taxPayable)}
                        </div>
                      </div>
                    </div>
                    <div className="mt-2.5 bg-yadea-orange/10 p-2.5 rounded-lg border border-yadea-orange/25">
                      <span className="block text-yadea-dark mb-1 text-[11px] font-bold">
                        Total Value Including Sales Tax
                      </span>
                      <div className="w-full p-2 bg-white border border-yadea-orange rounded font-black text-yadea-dark text-base text-right">
                        Rs {formatMoney(totalIncl)}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Saved invoices live in the Invoice Dashboard (top button). */}
              <div className="bg-yadea-orange/[0.06] border border-yadea-orange/20 rounded-xl p-4 text-xs">
                <div className="font-bold text-slate-800 flex items-center gap-2 mb-1">
                  <FaChartLine className="text-yadea-orange" /> Looking for saved invoices?
                </div>
                <p className="text-[11px] text-slate-600 leading-relaxed">
                  Open the <button type="button" onClick={() => setView('dashboard')} className="font-bold text-yadea-dark underline underline-offset-2 hover:text-yadea-orange">Invoice Dashboard</button> from
                  the top bar to browse full history with date filters and prices.
                </p>
              </div>

              <div className="bg-slate-900 text-slate-300 rounded-xl p-4 text-xs space-y-1.5 shadow-sm">
                <div className="font-bold text-white flex items-center gap-1.5">
                  <FaCircleInfo className="text-yadea-orange text-sm" /> Live Interactive Editor
                </div>
                <p className="leading-relaxed">
                  Fill the form above or type directly on any dotted line inside the document
                  preview - both stay in sync. Press Save to persist the invoice to the database.
                </p>
              </div>
            </div>

            {/* -------------------- Document canvas -------------------- */}
            <div className="lg:col-span-8">
              <div className="inv-scroll-host flex justify-start md:justify-center items-start overflow-x-auto pb-6 preview-x">
                <div className="w-full min-w-[640px] max-w-[780px] px-0.5">
                  <div
                    ref={docRef}
                    className="inv-page bg-white shadow-xl shadow-slate-900/10 rounded-2xl text-slate-900 relative flex flex-col justify-between overflow-hidden ring-1 ring-slate-200 w-full"
                    style={{ minHeight: 950 }}
                  >
                    <div>
                      {/* Header banner: orange band + black corner sweep so the
                          orange shield always sits on black and stays visible */}
                      <div className="relative bg-white">
                        <div className="w-full relative overflow-hidden leading-none" style={{ height: 112 }}>
                          <svg className="w-full h-full" viewBox="0 0 700 112" preserveAspectRatio="none">
                            {/* Brand-orange main band */}
                            <path d="M0,0 H700 V68 C590,102 430,112 305,107 C185,102 85,95 0,66 Z" fill="#EB5F1B" />
                            {/* White separation sliver along the sweep edge */}
                            <path d="M392,0 C512,38 626,78 700,116 L700,105 C624,68 518,29 402,-4 Z" fill="#ffffff" />
                            {/* Black corner sweep hosting the shield - extended left
                                and down so the logo keeps clear space off the corner */}
                            <path d="M400,0 C515,36 628,76 700,112 L700,0 Z" fill="#111827" />
                          </svg>

                          <div className="absolute top-4 left-7 right-7 flex justify-between items-start">
                            <h1 className="text-2xl md:text-[27px] font-black tracking-wide text-white drop-shadow-sm leading-tight">
                              Yadea Hussain Motors
                            </h1>
                            <div className="flex flex-col items-center pt-1 pr-5 shrink-0">
                              <YadeaLogo wordmark={false} className="w-10 h-auto" />
                              <span className="text-[9px] font-black tracking-[0.3em] mt-1 text-white">YADEA</span>
                            </div>
                          </div>
                        </div>

                        {/* Address ribbon (black to separate from the orange band) */}
                        <div className="bg-yadea-black text-white text-center text-xs md:text-sm font-bold py-1.5 px-4 tracking-wide">
                          Plaza # 27, Mini Ext 1, Bahria Town Phase 7, Rawalpindi.
                        </div>
                      </div>

                      {/* Meta row */}
                      <div className="px-7 pt-4 pb-2">
                        <div className="flex justify-between items-start pb-2 border-b-2 border-slate-900">
                          <div className="w-2/5">
                            <input
                              type="text"
                              value={form.invoiceNo}
                              onChange={setField('invoiceNo')}
                              className="inv-bare font-mono font-extrabold text-xl text-green-800 tracking-[0.2em] w-28 text-left bg-transparent"
                              aria-label="Invoice number"
                            />
                            <div className="text-xs font-bold text-slate-800 leading-tight">Invoice No</div>
                            <div className="text-[11px] font-bold text-slate-800 mt-1 flex items-center gap-1">
                              <span>STRN Number:</span>
                              <input
                                type="text"
                                value={form.strn}
                                onChange={setField('strn')}
                                className="inv-bare font-mono text-slate-900 font-bold w-32 bg-transparent"
                                aria-label="STRN number"
                              />
                            </div>
                          </div>

                          <div className="w-1/5 text-center pt-2">
                            <h2 className="text-lg md:text-xl font-black text-yadea-orange tracking-tight uppercase whitespace-nowrap">
                              Sales Tax Invoice
                            </h2>
                          </div>

                          <div className="w-2/5 text-right pt-2 flex justify-end items-end gap-1 text-xs font-bold text-slate-800">
                            <span className="text-sm">Dated</span>
                            <input
                              type="text"
                              placeholder="DD/MM/YYYY"
                              value={form.dated}
                              onChange={setField('dated')}
                              className="inv-underlined text-xs w-28 px-1 text-center font-bold"
                              aria-label="Invoice date"
                            />
                          </div>
                        </div>

                        {/* M/S line */}
                        <div className="mt-3 mb-4 flex items-baseline gap-2 text-sm md:text-base font-bold text-slate-900">
                          <span className="tracking-wide">M/S</span>
                          <input
                            type="text"
                            placeholder="Customer Name / Buyer Name"
                            value={form.ms}
                            onChange={setField('ms')}
                            className="inv-underlined flex-1 font-semibold px-2 text-sm md:text-base"
                            aria-label="Customer name"
                          />
                        </div>

                        {/* Rounded invoice table */}
                        <div className="inv-table-wrap mt-3">
                          <table className="inv-table">
                            <thead>
                              <tr>
                                <th style={{ width: '8%' }}>Qty</th>
                                <th style={{ width: '36%' }}>DESCRIPTION</th>
                                <th style={{ width: '14%' }}>
                                  Value Excluding
                                  <br />
                                  Sales Tax
                                </th>
                                <th style={{ width: '12%' }}>
                                  Rate Of
                                  <br />
                                  Sales Tax
                                </th>
                                <th style={{ width: '14%' }}>
                                  Sales Tax
                                  <br />
                                  Payable
                                </th>
                                <th style={{ width: '16%' }}>
                                  Value Including
                                  <br />
                                  Sales Tax
                                </th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr style={{ height: 330 }}>
                                <td className="text-center font-bold text-slate-900 pt-4">
                                  <input
                                    type="text"
                                    value={form.qty}
                                    onChange={setField('qty')}
                                    className="inv-bare w-full text-center font-bold bg-transparent"
                                    aria-label="Quantity"
                                  />
                                </td>
                                <td className="pt-3 px-3">
                                  <div className="space-y-4 text-xs font-bold text-slate-800">
                                    {DESC_ROWS.map((row) => (
                                      <div key={row.key} className="flex items-center gap-1">
                                        <span className="w-20 font-bold text-slate-900 shrink-0">{row.label}</span>
                                        <input
                                          type="text"
                                          placeholder={row.placeholder}
                                          value={form[row.key]}
                                          onChange={setField(row.key)}
                                          className="inv-dotted flex-1 px-1 py-0 font-medium"
                                          aria-label={row.label}
                                        />
                                      </div>
                                    ))}
                                  </div>
                                </td>
                                <td className="text-right pt-4 px-2 font-semibold">
                                  <input
                                    type="text"
                                    placeholder="0.00"
                                    value={form.valueExcl}
                                    onChange={setField('valueExcl')}
                                    className="inv-bare w-full text-right font-bold text-slate-900 bg-transparent"
                                    aria-label="Value excluding sales tax"
                                  />
                                </td>
                                <td className="text-center pt-4 px-1 font-semibold">
                                  <input
                                    type="text"
                                    placeholder="18%"
                                    value={form.taxRate}
                                    onChange={setField('taxRate')}
                                    className="inv-bare w-full text-center font-bold text-slate-900 bg-transparent"
                                    aria-label="Rate of sales tax"
                                  />
                                </td>
                                <td className="text-right pt-4 px-2 font-semibold text-slate-900">
                                  {formatMoney(taxPayable)}
                                </td>
                                <td className="text-right pt-4 px-2 font-black text-slate-900">
                                  {formatMoney(totalIncl)}
                                </td>
                              </tr>

                              <tr className="inv-total-row">
                                <td colSpan={2} className="text-center font-extrabold text-slate-900 text-sm py-2">
                                  Total
                                </td>
                                <td className="text-right px-2 font-bold py-2">{formatMoney(totalExcl)}</td>
                                <td className="py-2" />
                                <td className="text-right px-2 font-bold py-2">{formatMoney(taxPayable)}</td>
                                <td className="text-right px-2 font-black py-2 text-yadea-dark text-sm">
                                  {formatMoney(totalIncl)}
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>

                    {/* Footer */}
                    <div className="px-7 pt-4 pb-4 relative">
                      <div className="absolute bottom-0 left-0 w-40 h-24 pointer-events-none overflow-hidden z-0">
                        <svg className="w-full h-full" viewBox="0 0 160 100" preserveAspectRatio="none">
                          <path d="M -10,110 L 140,110 Q 70,30 -10,10 Z" fill="#EB5F1B" />
                          <path d="M -10,110 L 155,110 Q 100,60 -10,50 Z" fill="#111827" />
                        </svg>
                      </div>

                      <div className="flex justify-between items-end relative z-10 pl-24 pr-2">
                        <div className="text-left pb-1">
                          <p className="text-xs font-semibold text-slate-700 italic mb-0.5">For &amp; on Behalf of</p>
                          <h3 className="text-base md:text-lg font-black text-yadea-orange tracking-tight">
                            Yadea Hussain Motors
                          </h3>
                        </div>
                        <div className="text-center w-48">
                          <div className="border-b-2 border-slate-900 h-10" />
                          <span className="text-xs font-bold text-slate-900 tracking-wider uppercase mt-1 block">
                            Signature
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          )}
        </div>
      </div>
    </>
  );
}
