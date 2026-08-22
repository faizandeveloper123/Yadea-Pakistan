import { useCallback, useEffect, useRef, useState, type ChangeEvent } from 'react';
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
  const [listSearch, setListSearch] = useState('');

  const docRef = useRef<HTMLDivElement | null>(null);

  /* While this page is mounted every print (button or Ctrl+P) outputs only
     the invoice sheet - the CSS below hides everything else. */
  useEffect(() => {
    document.body.classList.add('inv-printing');
    return () => document.body.classList.remove('inv-printing');
  }, []);

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

  /* Saved-invoice listing (debounced server-side search). */
  const loadInvoices = useCallback(async () => {
    setLoadingList(true);
    try {
      const params: { search?: string; created_by?: number } = {};
      if (listSearch.trim()) params.search = listSearch.trim();
      if (user && user.user_type !== 'Admin') params.created_by = user.id;
      const res = await api.listInvoices(params);
      setInvoices(res.data);
    } catch (err) {
      onNotify(`Failed to load saved invoices: ${(err as Error).message}`);
    } finally {
      setLoadingList(false);
    }
  }, [listSearch, user, onNotify]);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadInvoices(), 250);
    return () => window.clearTimeout(timer);
  }, [loadInvoices]);

  /* ------------------------- live calculations ------------------------ */

  const qtyNum = parseNum(form.qty) || 1;
  const valueExcl = parseNum(form.valueExcl);
  const taxRate = parseNum(form.taxRate);
  const totalExcl = valueExcl * qtyNum;
  const taxPayable = totalExcl * (taxRate / 100);
  const totalIncl = totalExcl + taxPayable;

  const setField =
    (key: keyof InvoiceFormState) =>
    (e: ChangeEvent<HTMLInputElement>) =>
      setForm((prev) => ({ ...prev, [key]: e.target.value }));

  /* ----------------------------- actions ------------------------------ */

  const handleNew = () => {
    setEditingId(null);
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
        <div className="max-w-[1440px] mx-auto px-3 md:px-6 py-5">
          {/* Page heading + action bar */}
          <div className="inv-no-print flex flex-wrap items-center justify-between gap-3 mb-5">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-yadea-black flex items-center justify-center shadow">
                <YadeaLogo wordmark={false} className="h-6 w-auto" />
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
                <span className="inline-flex items-center gap-1.5 bg-yadea-orange/10 text-yadea-dark border border-yadea-orange/30 rounded-md px-2.5 py-1.5 text-[11px] font-bold">
                  <FaPenToSquare className="text-[10px]" />
                  Editing #{form.invoiceNo || editingId}
                </span>
              )}
              <button
                type="button"
                onClick={handleNew}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-md border border-slate-300 shadow-sm transition"
              >
                <FaPlus className="text-[10px]" /> New
              </button>
              <button
                type="button"
                onClick={handleSave}
                disabled={!canEdit || saving}
                title={canEdit ? 'Save invoice to database' : 'You do not have edit permission'}
                className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-yadea-orange hover:bg-yadea-dark text-white text-xs font-bold rounded-md shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <FaFloppyDisk className={saving ? 'animate-pulse' : ''} />
                {saving ? 'Saving...' : editingId !== null ? 'Update' : 'Save'}
              </button>
              <button
                type="button"
                onClick={handlePrint}
                className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-md border border-slate-300 shadow-sm transition"
              >
                <FaPrint /> Print
              </button>
              <button
                type="button"
                onClick={() => void handleDownloadPdf()}
                disabled={!canExport || pdfBusy}
                title={canExport ? 'Download as PDF' : 'You do not have export permission'}
                className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-md shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <FaFilePdf className={pdfBusy ? 'animate-pulse text-yadea-orange' : 'text-yadea-orange'} />
                {pdfBusy ? 'Preparing...' : 'Download PDF'}
              </button>
            </div>
          </div>

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

              {/* Saved invoices (database) */}
              <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div className="flex items-center justify-between mb-2.5">
                  <h2 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                    Saved Invoices
                    <span className="text-[10px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded font-bold">
                      {loadingList ? '...' : invoices.length}
                    </span>
                  </h2>
                  <button
                    type="button"
                    onClick={() => void loadInvoices()}
                    className="text-slate-400 hover:text-yadea-orange transition p-1"
                    aria-label="Refresh invoice list"
                    title="Refresh"
                  >
                    <FaRotateRight className="text-xs" />
                  </button>
                </div>

                <div className="relative mb-2">
                  <FaMagnifyingGlass className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[11px]" />
                  <input
                    type="text"
                    placeholder="Search by number, customer, bike..."
                    value={listSearch}
                    onChange={(e) => setListSearch(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-200 rounded-md pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:border-yadea-orange focus:ring-1 focus:ring-yadea-orange/40"
                  />
                </div>

                <div className="max-h-64 overflow-y-auto divide-y divide-slate-100 -mx-1">
                  {loadingList ? (
                    <p className="text-[11px] text-slate-400 text-center py-4">Loading invoices...</p>
                  ) : invoices.length === 0 ? (
                    <p className="text-[11px] text-slate-400 text-center py-4 leading-relaxed">
                      No invoices saved yet.
                      <br />
                      Fill the form and press Save to store one in the database.
                    </p>
                  ) : (
                    invoices.map((inv) => (
                      <div
                        key={inv.id}
                        className={`group flex items-center gap-2 py-2 px-1 ${
                          editingId === inv.id ? 'bg-yadea-orange/5' : ''
                        }`}
                      >
                        <button
                          type="button"
                          onClick={() => handleLoad(inv)}
                          className="flex-1 min-w-0 text-left"
                          title="Load into editor"
                        >
                          <div className="flex items-center gap-2">
                            <span className="font-mono font-extrabold text-xs text-green-800">
                              #{inv.invoice_no}
                            </span>
                            <span className="text-[11px] font-semibold text-slate-800 truncate">
                              {inv.customer_name || 'Unnamed customer'}
                            </span>
                          </div>
                          <div className="text-[10px] text-slate-400 mt-0.5 truncate">
                            {inv.dated || 'No date'} • Qty {inv.qty} • Rs{' '}
                            {formatMoney(Number(inv.value_incl) || 0)}
                            {inv.motorcycle ? ` • ${inv.motorcycle}` : ''}
                          </div>
                        </button>
                        {canDelete && (
                          <button
                            type="button"
                            onClick={() => void handleDelete(inv)}
                            className="opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-600 transition p-1 shrink-0"
                            aria-label={`Delete invoice ${inv.invoice_no}`}
                            title="Delete"
                          >
                            <FaRegTrashCan className="text-xs" />
                          </button>
                        )}
                      </div>
                    ))
                  )}
                </div>
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
                <div className="w-full min-w-[680px] max-w-[760px]">
                  <div
                    ref={docRef}
                    className="inv-page bg-white shadow-xl rounded-xl text-slate-900 relative flex flex-col justify-between overflow-hidden border border-slate-300 w-full"
                    style={{ minHeight: 960 }}
                  >
                    <div>
                      {/* Header banner */}
                      <div className="relative bg-white">
                        <div className="w-full relative overflow-hidden leading-none" style={{ height: 110 }}>
                          <svg className="w-full h-full" viewBox="0 0 700 110" preserveAspectRatio="none">
                            <path d="M 0,0 L 700,0 L 700,55 C 640,105 480,110 380,102 C 240,90 80,105 0,70 Z" fill="#111827" />
                            <path d="M 460,0 Q 560,35 700,105 L 700,110 Q 550,45 440,0 Z" fill="#ffffff" />
                            <path d="M 475,0 C 570,30 650,70 700,110 L 700,40 C 620,10 540,0 475,0 Z" fill="#EB5F1B" />
                          </svg>

                          <div className="absolute top-3 left-6 right-6 flex justify-between items-start text-white">
                            <div className="pt-1">
                              <h1 className="text-2xl md:text-3xl font-black tracking-wide drop-shadow-sm">
                                Yadea Hussain Motors
                              </h1>
                            </div>
                            <div className="flex flex-col items-center mr-4 pt-0.5">
                              <YadeaLogo wordmark={false} className="w-9 h-auto" />
                              <span className="text-[9px] font-black tracking-[0.25em] mt-0.5 text-white">
                                YADEA
                              </span>
                            </div>
                          </div>
                        </div>

                        {/* Address ribbon */}
                        <div className="bg-yadea-orange text-white text-center text-xs md:text-sm font-bold py-1 px-4 tracking-wide">
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
                              <tr style={{ height: 360 }}>
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
                          <path d="M -10,110 L 140,110 Q 70,30 -10,10 Z" fill="#111827" />
                          <path d="M -10,110 L 155,110 Q 100,60 -10,50 Z" fill="#EB5F1B" />
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
        </div>
      </div>
    </>
  );
}
