import React, { useEffect, useRef, useState } from 'react';
import type { IconType } from 'react-icons';
import {
  FaBuilding,
  FaCheck,
  FaChevronRight,
  FaCircleCheck,
  FaClipboardCheck,
  FaCloudArrowUp,
  FaDiagramProject,
  FaFileCsv,
  FaUserGroup,
  FaXmark,
} from 'react-icons/fa6';
import type { ImportedContactInput } from '../types';

const steps = [
  { num: 1, title: 'Start', subtitle: 'Select objects to import' },
  { num: 2, title: 'Upload', subtitle: 'Upload file & options' },
  { num: 3, title: 'Map', subtitle: 'Map columns to fields' },
  { num: 4, title: 'Verify', subtitle: 'Review & confirm' },
];

interface ImportObject {
  key: string;
  title: string;
  description: string;
  icon: IconType;
}

const importObjects: ImportObject[] = [
  {
    key: 'Contacts',
    title: 'Contacts',
    description: 'Contains contact records and their associated details.',
    icon: FaUserGroup,
  },
  {
    key: 'Opportunities',
    title: 'Opportunities',
    description: 'Includes deals, their stages, statuses, and pipeline progress.',
    icon: FaDiagramProject,
  },
  {
    key: 'Companies',
    title: 'Companies',
    description: 'Contains businesses, their details, and associated contact information.',
    icon: FaBuilding,
  },
];

const mappingRows = [
  { col: 'First Name', sample: 'Usman', field: 'First Name' },
  { col: 'Last Name', sample: 'Tariq', field: 'Last Name' },
  { col: 'Phone', sample: '+92 300 8899771', field: 'Phone Number' },
  { col: 'Email', sample: 'usman.tariq@apex.com', field: 'Email Address' },
  { col: 'Business Name', sample: 'Apex Global Logistics', field: 'Business Name' },
  { col: 'Tags', sample: 'hot lead', field: 'Tags' },
];

const previewContacts: ImportedContactInput[] = [
  {
    name: 'Usman Tariq',
    phone: '+92 300 8899771',
    email: 'usman.tariq@apex.com',
    business: 'Apex Global Logistics',
    tag: 'hot lead',
    color: 'bg-amber-200 text-amber-800',
  },
  {
    name: 'Sara Khan',
    phone: '+92 321 4455662',
    email: 'sara.k@innovate.pk',
    business: 'Innovate Digital',
    tag: 'customer',
    color: 'bg-purple-200 text-purple-800',
  },
  {
    name: 'Bilal Ahmad',
    phone: '+92 333 1122334',
    email: 'bilal@techventures.io',
    business: 'TechVentures',
    tag: 'warm lead',
    color: 'bg-emerald-200 text-emerald-800',
  },
];

interface ImportWizardProps {
  open: boolean;
  onClose: () => void;
  onImport: (batch: ImportedContactInput[]) => void;
  onNotify: (msg: string) => void;
}

function ImportWizard({ open, onClose, onImport, onNotify }: ImportWizardProps) {
  const [step, setStep] = useState(1);
  const [selected, setSelected] = useState<Set<string>>(new Set(['Contacts']));
  const [fileName, setFileName] = useState('contacts_import_batch.csv');
  const [fileSizeText, setFileSizeText] = useState('3 leads ready to process');
  const [fileReady, setFileReady] = useState(false);
  const [headerRow, setHeaderRow] = useState(true);
  const [updateExisting, setUpdateExisting] = useState(false);
  const [consent, setConsent] = useState(true);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (open) {
      setStep(1);
      setSelected(new Set(['Contacts']));
      setFileName('contacts_import_batch.csv');
      setFileSizeText('3 leads ready to process');
      setFileReady(false);
      setConsent(true);
    }
  }, [open]);

  if (!open) return null;

  const toggleObject = (key: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (key === 'Contacts') {
        next.add(key);
        return next;
      }
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setFileName(file.name);
    setFileSizeText(`${(file.size / 1024).toFixed(1)} KB • Ready to map fields`);
    setFileReady(true);
    onNotify(`File "${file.name}" uploaded successfully`);
  };

  const loadDemoCsv = () => {
    setFileName('contacts_import_batch.csv');
    setFileSizeText('3 new records ready for import');
    setFileReady(true);
    onNotify('Demo Contacts file loaded!');
  };

  const goToStep = (n: number) => {
    setStep(Math.min(4, Math.max(1, n)));
  };

  const handleNext = () => {
    if (step < 4) {
      setStep(step + 1);
    } else {
      executeImport();
    }
  };

  const executeImport = () => {
    if (!consent) {
      onNotify('Please confirm the consent agreement before importing.');
      return;
    }
    onImport(previewContacts);
    onNotify(`Successfully imported ${previewContacts.length} contacts!`);
    onClose();
  };

  const stepIconClass = (n: number) => {
    if (n === step) {
      return 'w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shadow-sm';
    }
    if (n < step) {
      return 'w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center';
    }
    return 'w-7 h-7 rounded-full border-2 border-slate-300 bg-white text-slate-500 text-xs font-bold flex items-center justify-center';
  };

  const stepTitleClass = (n: number) => {
    if (n === step) return 'text-xs font-bold text-blue-600';
    if (n < step) return 'text-xs font-semibold text-slate-700';
    return 'text-xs font-semibold text-slate-600';
  };

  const headerOpacity = (n: number) => (n <= step ? '' : 'opacity-50');

  return (
    <div className="fixed inset-0 z-50 flex-col flex">
      <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" aria-hidden="true" />

      <div className="relative flex flex-col h-full w-full">
        {/* Top Wizard Navigation Header */}
        <div className="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
          <div>
            <h2 className="text-xl font-bold text-slate-900">Imports</h2>
            <p className="text-xs text-slate-500 mt-0.5">Import contacts, opportunities and custom objects</p>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600 p-2 rounded-lg hover:bg-slate-100 transition"
            aria-label="Close"
          >
            <FaXmark className="text-lg" />
          </button>
        </div>

        {/* Stepper Progress Bar */}
        <div className="bg-slate-50/70 border-b border-slate-200 px-6 py-3.5 flex-shrink-0">
          <div className="max-w-4xl mx-auto flex items-center justify-between relative">
            {steps.map((s, idx) => (
              <React.Fragment key={s.num}>
                <div
                  className={`flex items-center space-x-2.5 z-10 cursor-pointer ${headerOpacity(s.num)}`}
                  onClick={() => goToStep(s.num)}
                >
                  <div className={stepIconClass(s.num)}>
                    {s.num < step ? <FaCheck className="text-[10px]" /> : s.num}
                  </div>
                  <div className="flex flex-col">
                    <span className={stepTitleClass(s.num)}>{s.title}</span>
                    <span className="text-[11px] text-slate-400 hidden sm:inline">
                      {s.subtitle}
                    </span>
                  </div>
                </div>
                {idx < steps.length - 1 && <div className="flex-1 h-0.5 bg-slate-200 mx-3" />}
              </React.Fragment>
            ))}
          </div>
        </div>

        {/* Wizard Scrollable Body */}
        <div className="flex-1 overflow-y-auto bg-slate-50 p-6">
          <div className="max-w-4xl mx-auto space-y-6">
            {step === 1 && (
              <>
                <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                  <h3 className="text-sm font-semibold text-slate-800 mb-4">
                    Select objects to import
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {importObjects.map((obj) => {
                      const isSelected = selected.has(obj.key);
                      const Icon = obj.icon;
                      return (
                        <div
                          key={obj.key}
                          onClick={() => toggleObject(obj.key)}
                          className={
                            isSelected
                              ? 'border-2 border-blue-600 bg-blue-50/40 rounded-xl p-4 cursor-pointer relative transition hover:shadow-sm'
                              : 'border border-slate-200 bg-white hover:border-slate-300 rounded-xl p-4 cursor-pointer relative transition'
                          }
                        >
                          <div className="flex items-start justify-between">
                            <div
                              className={
                                isSelected
                                  ? 'w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-sm mb-3'
                                  : 'w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-sm mb-3'
                              }
                            >
                              <Icon />
                            </div>
                            <input
                              type="checkbox"
                              checked={isSelected}
                              readOnly
                              className="h-4 w-4 text-blue-600 rounded border-slate-300"
                            />
                          </div>
                          <h4 className="text-xs font-bold text-slate-900 mb-1">{obj.title}</h4>
                          <p className="text-[11px] text-slate-500 leading-relaxed">
                            {obj.description}
                          </p>
                        </div>
                      );
                    })}
                  </div>
                </div>

                <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                  <h3 className="text-sm font-semibold text-slate-800 mb-2">Previous imports</h3>
                  <p className="text-xs text-slate-600 mb-3">
                    View previous imports in bulk actions.
                  </p>
                  <a
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      onNotify('Navigating to bulk actions history...');
                    }}
                    className="text-xs text-blue-600 hover:underline font-semibold inline-flex items-center gap-1"
                  >
                    View bulk actions
                    <FaChevronRight className="text-[10px]" />
                  </a>
                </div>
              </>
            )}

            {step === 2 && (
              <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                <h3 className="text-sm font-semibold text-slate-800">Upload your file</h3>

                <div
                  onClick={() => fileInputRef.current?.click()}
                  className="border-2 border-dashed border-slate-300 hover:border-blue-500 rounded-xl p-8 text-center bg-slate-50/60 cursor-pointer transition group"
                >
                  <FaCloudArrowUp className="text-3xl text-slate-400 group-hover:text-blue-600 transition mb-2 mx-auto" />
                  <p className="text-xs font-semibold text-slate-700">
                    Click to upload or drag & drop file
                  </p>
                  <p className="text-[11px] text-slate-400 mt-1">
                    Supported formats: .CSV, .XLS, .XLSX (Up to 10MB)
                  </p>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv,.xls,.xlsx"
                    className="hidden"
                    onChange={handleFileSelect}
                    aria-label="Upload import file"
                  />
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                  <span className="text-xs text-slate-500">
                    Don't have a file ready? Use demo test data:
                  </span>
                  <button
                    onClick={loadDemoCsv}
                    className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md text-xs font-semibold transition flex items-center gap-1.5"
                  >
                    <FaFileCsv className="text-emerald-600" />
                    Load Demo Contacts File
                  </button>
                </div>

                {fileReady && (
                  <div className="flex bg-emerald-50 border border-emerald-200 rounded-lg p-3.5 items-center justify-between text-xs">
                    <div className="flex items-center space-x-3">
                      <FaFileCsv className="text-emerald-600 text-lg" />
                      <div>
                        <div className="font-semibold text-slate-800">{fileName}</div>
                        <div className="text-[11px] text-slate-500">{fileSizeText}</div>
                      </div>
                    </div>
                    <span className="bg-emerald-600 text-white text-[10px] px-2 py-0.5 rounded font-semibold">
                      Ready
                    </span>
                  </div>
                )}

                <div className="pt-4 border-t border-slate-100 space-y-2">
                  <h4 className="text-xs font-semibold text-slate-700 mb-2">
                    File Configuration
                  </h4>
                  <label className="flex items-center space-x-2 text-xs text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={headerRow}
                      onChange={(e) => setHeaderRow(e.target.checked)}
                      className="rounded border-slate-300 text-blue-600"
                    />
                    <span>First row contains column headers</span>
                  </label>
                  <label className="flex items-center space-x-2 text-xs text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={updateExisting}
                      onChange={(e) => setUpdateExisting(e.target.checked)}
                      className="rounded border-slate-300 text-blue-600"
                    />
                    <span>Update existing contacts if phone or email matches</span>
                  </label>
                </div>
              </div>
            )}

            {step === 3 && (
              <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-semibold text-slate-800">
                      Map CSV Columns to CRM Contact Fields
                    </h3>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Ensure your file columns correspond correctly with dashboard properties.
                    </p>
                  </div>
                  <span className="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-full border border-blue-100 font-semibold">
                    6 Columns Mapped
                  </span>
                </div>

                <div className="overflow-x-auto border border-slate-200 rounded-lg">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                      <tr>
                        <th className="py-2.5 px-4">Column Header in File</th>
                        <th className="py-2.5 px-4">Sample Data (Row 1)</th>
                        <th className="py-2.5 px-4">Maps to CRM Field</th>
                        <th className="py-2.5 px-4 text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 text-slate-700">
                      {mappingRows.map((row) => (
                        <tr key={row.col}>
                          <td className="py-2.5 px-4 font-semibold text-slate-800">{row.col}</td>
                          <td className="py-2.5 px-4 text-slate-500">{row.sample}</td>
                          <td className="py-2.5 px-4">
                            <select
                              className="w-full border border-slate-300 rounded px-2 py-1 text-xs bg-white"
                              defaultValue={row.field}
                            >
                              <option>{row.field}</option>
                              <option>Last Name</option>
                              <option>Phone</option>
                              <option>Email</option>
                              <option>Business Name</option>
                              <option>Tags</option>
                            </select>
                          </td>
                          <td className="py-2.5 px-4 text-center text-emerald-600 font-semibold">
                            <FaCircleCheck />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {step === 4 && (
              <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                <div className="flex items-center space-x-3 pb-3 border-b border-slate-100">
                  <div className="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg">
                    <FaClipboardCheck />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-slate-900">Review & Confirm Import</h3>
                    <p className="text-xs text-slate-500">
                      Verify details before importing records into CRM Contacts.
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <span className="text-slate-400 text-[11px] block font-medium">
                      Selected Object
                    </span>
                    <span className="font-bold text-slate-800">
                      {[...selected].join(', ') || 'None'}
                    </span>
                  </div>
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <span className="text-slate-400 text-[11px] block font-medium">
                      Source File
                    </span>
                    <span className="font-bold text-slate-800 truncate block">{fileName}</span>
                  </div>
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <span className="text-slate-400 text-[11px] block font-medium">
                      Records Detected
                    </span>
                    <span className="font-bold text-emerald-600">3 Valid Records</span>
                  </div>
                </div>

                <div>
                  <h4 className="text-xs font-semibold text-slate-700 mb-2">
                    Parsed Contacts Sample Preview
                  </h4>
                  <div className="border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-xs text-left">
                      <thead className="bg-slate-50 text-slate-600 font-medium">
                        <tr>
                          <th className="p-2.5">Name</th>
                          <th className="p-2.5">Phone</th>
                          <th className="p-2.5">Email</th>
                          <th className="p-2.5">Company</th>
                          <th className="p-2.5">Tag</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {previewContacts.map((c) => (
                          <tr key={c.name}>
                            <td className="p-2.5 font-medium text-slate-800">{c.name}</td>
                            <td className="p-2.5 text-slate-600">{c.phone}</td>
                            <td className="p-2.5 text-slate-600">{c.email}</td>
                            <td className="p-2.5 text-slate-600">{c.business}</td>
                            <td className="p-2.5">
                              <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px]">
                                {c.tag}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="pt-2">
                  <label className="flex items-start space-x-2 text-xs text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={consent}
                      onChange={(e) => setConsent(e.target.checked)}
                      className="mt-0.5 rounded border-slate-300 text-blue-600"
                    />
                    <span>
                      I confirm that all contacts in this list have provided explicit consent to
                      receive communications from my organization.
                    </span>
                  </label>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Wizard Sticky Navigation Footer Bar */}
        <div className="bg-white border-t border-slate-200 px-6 py-3.5 flex items-center justify-between flex-shrink-0">
          <button
            onClick={() => goToStep(step - 1)}
            disabled={step === 1}
            className="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Back
          </button>

          <div className="flex items-center space-x-2">
            <button
              onClick={onClose}
              className="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold rounded-lg transition"
            >
              Cancel
            </button>
            <button
              onClick={handleNext}
              className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition shadow-sm flex items-center space-x-1.5"
            >
              <span>
                {step === 4 ? 'Confirm & Import Contacts' : 'Next'}
              </span>
              {step === 4 ? (
                <FaCheck className="text-[10px]" />
              ) : (
                <FaChevronRight className="text-[10px]" />
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default ImportWizard;
