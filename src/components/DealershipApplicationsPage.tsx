import { useCallback, useEffect, useMemo, useState } from 'react';
import type { ChangeEvent } from 'react';
import {
  FaDownload,
  FaPaperPlane,
  FaRegTrashCan,
  FaStore,
  FaXmark,
} from 'react-icons/fa6';
import { api, type ApiPortalSubmission } from '../api';
import { useAuth } from '../auth';
import { PROVINCES, citiesForProvince } from '../data/pakistanCities';

interface PageProps {
  onNotify: (msg: string) => void;
}

const inputCls =
  'w-full bg-slate-50 border border-transparent focus:border-yadea-orange focus:bg-white rounded-lg px-3.5 py-2.5 text-sm text-slate-800 outline-none transition placeholder-slate-400';
const labelCls = 'block text-xs font-medium text-slate-600 mb-1';

function fmtDate(v: string | null): string {
  if (!v) return '-';
  const d = new Date(v.includes('T') ? v : v.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString();
}

function DealershipApplicationsPage({ onNotify }: PageProps) {
  const { user } = useAuth();
  const isAdmin = user?.user_type === 'Admin';

  const [form, setForm] = useState({
    salutation: 'Mr.',
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    businessName: '',
    address: '',
    yearsInBusiness: '',
    oemDealer: 'Choose Yes/No',
    province: '',
    city: '',
    propertyOwnership: '',
    structure: '',
    fileName: '',
  });
  const [consent, setConsent] = useState(false);
  const [saving, setSaving] = useState(false);

  const [rows, setRows] = useState<ApiPortalSubmission[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [viewing, setViewing] = useState<ApiPortalSubmission | null>(null);

  const cityOptions = useMemo(() => citiesForProvince(form.province), [form.province]);

  const setField =
    (key: keyof typeof form) =>
    (e: ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((prev) => ({ ...prev, [key]: e.target.value }));

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.listPortalSubmissions({ type: 'dealership' });
      setRows(res.data);
    } catch (err) {
      onNotify(`Failed to load applications: ${(err as Error).message}`);
    } finally {
      setLoading(false);
    }
  }, [onNotify]);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async () => {
    if (!form.firstName.trim() || !form.lastName.trim()) return onNotify('First and last name are required');
    if (!form.email.trim()) return onNotify('Email is required');
    if (!form.phone.trim()) return onNotify('Phone is required');
    if (!form.businessName.trim()) return onNotify('Business name is required');
    if (!form.address.trim()) return onNotify('Address is required');
    if (!form.yearsInBusiness.trim()) return onNotify('Years in business is required');
    if (!consent) return onNotify('Please accept the Privacy Policy first');

    setSaving(true);
    try {
      await api.createPortalSubmission({
        type: 'dealership',
        name: `${form.salutation} ${form.firstName.trim()} ${form.lastName.trim()}`.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        business_name: form.businessName.trim(),
        address: form.address.trim(),
        years_in_business: form.yearsInBusiness.trim(),
        oem_dealer: form.oemDealer === 'Choose Yes/No' ? '' : form.oemDealer,
        province: form.province,
        city: form.city,
        property_ownership: form.propertyOwnership,
        structure: form.structure,
        file_name: form.fileName,
        created_by: user?.id ?? null,
      });
      onNotify('Dealership application submitted');
      setForm((prev) => ({
        ...prev,
        firstName: '', lastName: '', email: '', phone: '', businessName: '', address: '',
        yearsInBusiness: '', oemDealer: 'Choose Yes/No', city: '', propertyOwnership: '',
        structure: '', fileName: '',
      }));
      setConsent(false);
      await load();
    } catch (err) {
      onNotify(`Submit failed: ${(err as Error).message}`);
    } finally {
      setSaving(false);
    }
  };

  const removeRow = async (row: ApiPortalSubmission) => {
    if (!isAdmin) return onNotify('Only admins can delete records');
    if (!window.confirm(`Delete application ${row.code}?`)) return;
    try {
      await api.deletePortalSubmission(row.id);
      onNotify(`Application ${row.code} deleted`);
      await load();
    } catch (err) {
      onNotify(`Delete failed: ${(err as Error).message}`);
    }
  };

  const exportCsv = () => {
    if (rows.length === 0) return onNotify('Nothing to export yet');
    let csv = 'Code,Name,Business,Email,Phone,City,Province,OEM,Years,Property,Structure,File,Submitted\n';
    rows.forEach((r) => {
      csv += `"${r.code}","${r.name}","${r.business_name}","${r.email}","${r.phone}","${r.city}","${r.province}","${r.oem_dealer}","${r.years_in_business}","${r.property_ownership}","${r.structure}","${r.file_name}","${fmtDate(r.created_at)}"\n`;
    });
    const link = document.createElement('a');
    link.href = `data:text/csv;charset=utf-8,${encodeURI(csv)}`;
    link.download = 'yadea_dealership_applications.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    onNotify('CSV exported');
  };

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((r) =>
      [r.code, r.name, r.business_name, r.email, r.phone, r.city, r.province]
        .join(' ')
        .toLowerCase()
        .includes(q)
    );
  }, [rows, search]);

  return (
    <div className="min-h-full bg-slate-100">
      {/* Hero */}
      <div
        className="relative h-52 md:h-64 flex items-center justify-center text-center px-4"
        style={{
          background:
            'linear-gradient(180deg, rgba(18,22,25,0.55) 0%, rgba(18,22,25,0.85) 100%), url(\'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=1600&q=80\') center/cover no-repeat #12161a',
        }}
      >
        <div>
          <h2 className="text-3xl md:text-4xl font-extrabold text-white tracking-tight">Dealership Applications</h2>
          <p className="text-slate-300 mt-2 text-sm font-light max-w-2xl mx-auto">
            Register new dealer partners — applications land here for review and follow-up.
          </p>
        </div>
      </div>

      <div className="max-w-5xl mx-auto px-3 md:px-6 -mt-10 pb-10 relative z-10 space-y-6">
        {/* Application form */}
        <div className="bg-white rounded-2xl shadow-xl p-5 md:p-8 border border-slate-100">
          <h3 className="text-xl font-bold text-slate-900 uppercase tracking-wide text-center mb-6">
            Dealership Application
          </h3>

          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
              <div className="md:col-span-3">
                <label className={labelCls}>Salutation</label>
                <select value={form.salutation} onChange={setField('salutation')} className={inputCls}>
                  <option>Mr.</option>
                  <option>Mrs.</option>
                  <option>Ms.</option>
                  <option>Dr.</option>
                </select>
              </div>
              <div className="md:col-span-4">
                <label className={labelCls}>First Name *</label>
                <input value={form.firstName} onChange={setField('firstName')} placeholder="First name" className={inputCls} />
              </div>
              <div className="md:col-span-5">
                <label className={labelCls}>Last Name *</label>
                <input value={form.lastName} onChange={setField('lastName')} placeholder="Last name" className={inputCls} />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Email Address *</label>
                <input type="email" value={form.email} onChange={setField('email')} placeholder="name@email.com" className={inputCls} />
              </div>
              <div>
                <label className={labelCls}>Phone *</label>
                <input value={form.phone} onChange={setField('phone')} placeholder="+92 3xx xxxxxxx" className={inputCls} />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Current Business Name *</label>
                <input value={form.businessName} onChange={setField('businessName')} placeholder="Business name" className={inputCls} />
              </div>
              <div>
                <label className={labelCls}>Address *</label>
                <input value={form.address} onChange={setField('address')} placeholder="Business address" className={inputCls} />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Years in Automotive Business *</label>
                <input type="number" min={0} max={60} value={form.yearsInBusiness} onChange={setField('yearsInBusiness')} placeholder="e.g. 8" className={inputCls} />
              </div>
              <div>
                <label className={labelCls}>Currently an OEM Dealer</label>
                <select value={form.oemDealer} onChange={setField('oemDealer')} className={inputCls}>
                  <option value="Choose Yes/No">Choose Yes/No</option>
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Province</label>
                <select
                  value={form.province}
                  onChange={(e) => setForm((prev) => ({ ...prev, province: e.target.value, city: '' }))}
                  className={inputCls}
                >
                  <option value="">Select Province</option>
                  {PROVINCES.map((p) => (
                    <option key={p} value={p}>{p}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelCls}>City</label>
                <select value={form.city} onChange={setField('city')} className={inputCls}>
                  <option value="">Select City</option>
                  {cityOptions.map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Property Ownership</label>
                <select value={form.propertyOwnership} onChange={setField('propertyOwnership')} className={inputCls}>
                  <option value="">Select Property Status</option>
                  <option>Property Owned</option>
                  <option>Property Rented</option>
                  <option>Property Leased</option>
                  <option>Property to be procured</option>
                </select>
              </div>
              <div>
                <label className={labelCls}>Structure</label>
                <select value={form.structure} onChange={setField('structure')} className={inputCls}>
                  <option value="">Select Structure Status</option>
                  <option>Completely Finished</option>
                  <option>Grey Structure Ready</option>
                  <option>Empty Plot</option>
                  <option>N/A</option>
                </select>
              </div>
            </div>

            <div>
              <label className={labelCls}>Attach File Here (file name reference)</label>
              <input value={form.fileName} onChange={setField('fileName')} placeholder="e.g. profile_documents.pdf" className={inputCls} />
            </div>

            <label className="flex items-start gap-2 pt-1 cursor-pointer">
              <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 w-4 h-4 accent-yadea-orange cursor-pointer" />
              <span className="text-xs text-slate-600 leading-relaxed">
                I have read the Privacy Policy and accept the processing of data for the purposes indicated.
              </span>
            </label>

            <div className="pt-2 flex justify-center">
              <button
                type="button"
                onClick={() => void submit()}
                disabled={saving}
                className="w-full md:w-1/2 bg-yadea-orange hover:bg-yadea-dark text-white font-bold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition uppercase tracking-wider text-sm flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <FaPaperPlane className="text-xs" />
                {saving ? 'Sending…' : 'Send'}
              </button>
            </div>
          </div>
        </div>

        {/* Received applications */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div className="p-4 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
            <span className="text-sm font-bold text-slate-700 flex items-center gap-2">
              <FaStore className="text-yadea-orange" /> Received Applications
              <span className="bg-slate-200 text-slate-600 text-[10px] px-2 py-0.5 rounded-full">{rows.length}</span>
            </span>
            <div className="flex items-center gap-2">
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search applications…"
                className="px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg outline-none focus:border-yadea-orange w-44"
              />
              <button onClick={exportCsv} className="text-xs bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5">
                <FaDownload className="text-[10px]" /> Export CSV
              </button>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-600">
              <thead className="bg-slate-100 text-slate-700 uppercase font-semibold border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3">Code</th>
                  <th className="px-4 py-3">Applicant / Business</th>
                  <th className="px-4 py-3">Contact</th>
                  <th className="px-4 py-3">City</th>
                  <th className="px-4 py-3">Submitted</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {loading ? (
                  <tr><td colSpan={6} className="px-4 py-8 text-center text-slate-400">Loading…</td></tr>
                ) : filtered.length === 0 ? (
                  <tr><td colSpan={6} className="px-4 py-8 text-center text-slate-400 font-medium">No applications yet.</td></tr>
                ) : (
                  filtered.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50 transition">
                      <td className="px-4 py-3 font-mono font-bold text-yadea-dark">{r.code}</td>
                      <td className="px-4 py-3">
                        <div className="font-semibold text-slate-800">{r.name}</div>
                        <div className="text-[11px] text-slate-500">{r.business_name}</div>
                      </td>
                      <td className="px-4 py-3">
                        <div>{r.email}</div>
                        <div className="text-[11px] text-slate-500">{r.phone}</div>
                      </td>
                      <td className="px-4 py-3">{r.city || '-'}</td>
                      <td className="px-4 py-3 text-slate-400 text-[11px]">{fmtDate(r.created_at)}</td>
                      <td className="px-4 py-3 text-right space-x-1.5 whitespace-nowrap">
                        <button onClick={() => setViewing(r)} className="text-slate-600 hover:text-yadea-orange text-[11px] font-bold px-2 py-1 bg-slate-100 rounded hover:bg-orange-50 transition">
                          View
                        </button>
                        {isAdmin && (
                          <button onClick={() => void removeRow(r)} className="text-red-500 hover:text-red-700 text-[11px] px-2 py-1 bg-red-50 rounded hover:bg-red-100 transition">
                            <FaRegTrashCan />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Details modal */}
      {viewing && (
        <div className="fixed inset-0 z-[80] bg-slate-900/50 backdrop-blur-[2px] flex items-center justify-center p-4 evee-fade-in">
          <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-pop">
            <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-bold text-slate-800 text-base">
                Application {viewing.code}
              </h3>
              <button onClick={() => setViewing(null)} className="text-slate-400 hover:text-slate-600 transition">
                <FaXmark className="text-lg" />
              </button>
            </div>
            <div className="p-5 space-y-1.5 text-xs max-h-[60vh] overflow-y-auto">
              {(
                [
                  ['Name', viewing.name],
                  ['Business', viewing.business_name],
                  ['Email', viewing.email],
                  ['Phone', viewing.phone],
                  ['Address', viewing.address],
                  ['Years in Business', viewing.years_in_business],
                  ['OEM Dealer', viewing.oem_dealer],
                  ['Province', viewing.province],
                  ['City', viewing.city],
                  ['Property', viewing.property_ownership],
                  ['Structure', viewing.structure],
                  ['File', viewing.file_name],
                  ['Submitted', fmtDate(viewing.created_at)],
                ] as const
              ).map(([k, v]) => (
                <div key={k} className="flex justify-between gap-3 py-1 border-b border-slate-100">
                  <span className="font-bold uppercase text-slate-400 shrink-0">{k}</span>
                  <span className="text-slate-800 text-right font-medium break-all">{v || '-'}</span>
                </div>
              ))}
            </div>
            <div className="px-5 py-3 border-t border-slate-200 flex justify-end">
              <button onClick={() => setViewing(null)} className="bg-slate-800 text-white text-xs px-4 py-2 rounded-lg font-semibold hover:bg-slate-900 transition">
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default DealershipApplicationsPage;
