import { useState } from 'react';
import { FaRegCircleCheck } from 'react-icons/fa6';
import { api } from '../api';
import { logActivity } from '../data/activityLog';
import { ensureCampaignsLoaded, campaignNameById } from '../data/campaigns';
import { deserializeFormFromUrl, type PublicFormPayload } from '../utils';
import { recordFormSubmission } from '../data/formsStore';

const DEFAULT_OPTIONS = ['Option 1', 'Option 2', 'Option 3'];

const TEXT_LIKE = [
  'text',
  'phone',
  'email',
  'date',
  'number',
  'monetary',
  'source',
  'score',
  'address',
  'city',
  'state',
  'country',
  'postal_code',
  'organization',
  'website',
];

function findField(elements: PublicFormPayload['elements'], re: RegExp) {
  return elements.find((el) => re.test(el.label.trim())) ?? null;
}

export default function PublicFormPage({ data }: { data: string }) {
  const form = deserializeFormFromUrl(data);
  const [values, setValues] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [errorMsg, setErrorMsg] = useState('');

  if (!form) {
    return (
      <div className="min-h-screen bg-slate-100 flex items-center justify-center p-6">
        <div className="bg-white border border-slate-200 rounded-xl shadow-sm p-8 max-w-sm text-center">
          <p className="font-semibold text-slate-700">Invalid form link</p>
          <p className="text-xs text-slate-400 mt-1">This link appears to be broken.</p>
        </div>
      </div>
    );
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus('submitting');
    setErrorMsg('');
    try {
      const firstName = values[findField(form.elements, /first\s*name/i)?.label ?? ''] ?? '';
      const lastName = values[findField(form.elements, /last\s*name/i)?.label ?? ''] ?? '';
      const fullNameEl = findField(form.elements, /^(full\s*)?name$/i);
      const fullName = values[fullNameEl?.label ?? ''] ?? '';
      let name = (firstName || lastName) ? `${firstName} ${lastName}`.trim() : (fullName || '');

      const email = values[findField(form.elements, /email/i)?.label ?? ''] ?? undefined;
      const phone = values[findField(form.elements, /phone/i)?.label ?? ''] ?? undefined;
      const business = values[findField(form.elements, /organization|business|company/i)?.label ?? ''] ?? undefined;

      // A contact always needs a name; fall back to the email local part (or a
      // generic label) so forms without a name field still create the lead.
      if (!name && email) name = email.split('@')[0];
      if (!name) name = 'Form Lead';

      const submittedAt = new Date().toISOString();
      const filled: Record<string, string> = {};
      for (const el of form.elements) {
        if (el.type === 'button') continue;
        const v = values[el.label];
        if (v && v.trim()) filled[el.label] = v.trim();
      }

      const res = await api.createContact({
        first_name: firstName || undefined,
        last_name: lastName || undefined,
        name,
        email: email || undefined,
        phone: phone || undefined,
        business_name: business || undefined,
        contact_type: 'Lead',
        tags: ['lead', form.name],
        custom_fields: { form_submissions: [{ formName: form.name, submittedOn: submittedAt, values: filled }] },
      });

      recordFormSubmission(form.name, form.elements);

      // Resolve the campaign name (fetched from the server) so the activity
      // tab can show which campaign this form belongs to. On localhost the
      // fetch fails silently and resolves to ''.
      if (form.campaignId && !campaignNameById(form.campaignId)) {
        await ensureCampaignsLoaded().catch(() => undefined);
      }
      const resolvedCampaign = form.campaignId ? campaignNameById(form.campaignId) : '';

      logActivity({
        type: 'form',
        title: 'Form submitted',
        detail: [
          `Form: ${form.name}`,
          resolvedCampaign ? `Campaign: ${resolvedCampaign}` : null,
          `Submitted: ${new Date(submittedAt).toLocaleString()}`,
        ]
          .filter(Boolean)
          .join('\n'),
        contactId: res.data.id,
      });
      setStatus('success');
    } catch (err) {
      setStatus('error');
      setErrorMsg((err as Error).message);
    }
  };

  return (
    <div
      className="min-h-screen bg-slate-100 flex items-center justify-center p-4 sm:p-6"
      style={{ background: `linear-gradient(135deg, ${form.header?.accentColor ?? '#6366F1'}22, #f1f5f9)` }}
    >
      <div className="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        {form.header && (
          <div className="relative">
            {form.header.image ? (
              <img
                src={form.header.image}
                alt={form.header.title}
                className="w-full h-36 sm:h-44 object-cover"
              />
            ) : (
              <div
                className="w-full h-36 sm:h-44"
                style={{
                  background: `linear-gradient(135deg, ${form.header.accentColor}99, ${form.header.accentColor})`,
                }}
              />
            )}
            {form.header.title && !form.header.hideTitle && (
              <div className="absolute inset-0 flex items-center justify-center text-center p-4">
                <span
                  className="text-white font-bold uppercase tracking-wider text-sm sm:text-base"
                  style={{
                    fontFamily: form.header.titleFont || undefined,
                    color: form.header.titleColor || undefined,
                  }}
                >
                  {form.header.title}
                </span>
              </div>
            )}
          </div>
        )}

        {status === 'success' ? (
          <div className="p-8 sm:p-10 text-center">
            <FaRegCircleCheck className="text-4xl text-emerald-500 mx-auto mb-3" />
            <h2 className="text-lg font-semibold text-slate-800">Thank you!</h2>
            <p className="text-sm text-slate-500 mt-1">Your submission has been received.</p>
          </div>
        ) : (
          <form onSubmit={submit} className="p-5 sm:p-6">
            <div className={form.columns === 2 ? 'grid grid-cols-2 gap-4' : ''}>
              {form.elements
                .filter((el) => el.type !== 'button')
                .map((el) => (
                  <div key={el.label} className={form.columns === 2 ? '' : 'mb-4'}>
                    {el.type !== 'checkbox' && el.type !== 'tnc' && (
                      <label className="block text-xs font-semibold text-slate-700 mb-1">
                        {el.label}
                        {el.required && <span className="text-rose-500">*</span>}
                      </label>
                    )}
                    {el.type === 'textarea' ? (
                      <textarea
                        rows={3}
                        value={values[el.label] ?? ''}
                        onChange={(e) => setValues((p) => ({ ...p, [el.label]: e.target.value }))}
                        placeholder={el.placeholder || 'Enter response...'}
                        className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:ring-1 focus:ring-blue-500 outline-none"
                      />
                    ) : el.type === 'single_dropdown' || el.type === 'multi_dropdown' || el.type === 'select' ? (
                      <select
                        value={values[el.label] ?? ''}
                        onChange={(e) => setValues((p) => ({ ...p, [el.label]: e.target.value }))}
                        className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:ring-1 focus:ring-blue-500 outline-none"
                      >
                        <option value="">{el.placeholder || 'Select an option'}</option>
                        {(el.options && el.options.length > 0 ? el.options : DEFAULT_OPTIONS).map((o) => (
                          <option key={o} value={o}>
                            {o}
                          </option>
                        ))}
                      </select>
                    ) : el.type === 'checkbox' ? (
                      <div className="space-y-1.5">
                        {(el.options && el.options.length > 0 ? el.options : DEFAULT_OPTIONS).map((o) => (
                          <label key={o} className="flex items-center space-x-2">
                            <input
                              type="checkbox"
                              value={o}
                              onChange={() => setValues((p) => ({ ...p, [el.label]: o }))}
                              className="rounded border-slate-300 text-blue-600"
                            />
                            <span className="text-sm text-slate-600 leading-tight">{o}</span>
                          </label>
                        ))}
                      </div>
                    ) : el.type === 'radio' ? (
                      <div className="space-y-1.5">
                        {(el.options && el.options.length > 0 ? el.options : DEFAULT_OPTIONS).map((o) => (
                          <label key={o} className="flex items-center space-x-2">
                            <input
                              type="radio"
                              name={el.label}
                              value={o}
                              onChange={() => setValues((p) => ({ ...p, [el.label]: o }))}
                              className="text-blue-600"
                            />
                            <span className="text-sm text-slate-600">{o}</span>
                          </label>
                        ))}
                      </div>
                    ) : TEXT_LIKE.includes(el.type) ? (
                      <input
                        type={el.type === 'email' ? 'email' : el.type === 'phone' ? 'tel' : 'text'}
                        value={values[el.label] ?? ''}
                        onChange={(e) => setValues((p) => ({ ...p, [el.label]: e.target.value }))}
                        placeholder={el.placeholder || 'Enter response...'}
                        className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:ring-1 focus:ring-blue-500 outline-none"
                      />
                    ) : (
                      <input
                        type="text"
                        value={values[el.label] ?? ''}
                        onChange={(e) => setValues((p) => ({ ...p, [el.label]: e.target.value }))}
                        placeholder={el.placeholder || 'Enter response...'}
                        className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:ring-1 focus:ring-blue-500 outline-none"
                      />
                    )}
                  </div>
                ))}
            </div>

            {status === 'error' && (
              <p className="text-xs text-rose-600 mt-2">{errorMsg || 'Something went wrong. Please try again.'}</p>
            )}

            <button
              type="submit"
              disabled={status === 'submitting'}
              className="w-full mt-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-medium py-2.5 rounded-md text-sm transition"
            >
              {status === 'submitting' ? 'Submitting...' : 'Submit'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
