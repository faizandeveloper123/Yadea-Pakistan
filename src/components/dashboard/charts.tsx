import { useEffect, useRef } from 'react';
import Chart from 'chart.js/auto';
import { FaArrowTrendUp, FaArrowTrendDown, FaPhone } from 'react-icons/fa6';
import { STATUS_COLORS, STATUS_META } from './widgetMeta';
import type { DealerLeadStatus } from '../../api';

const PALETTE = [
  '#3b82f6',
  '#8b5cf6',
  '#10b981',
  '#f59e0b',
  '#ef4444',
  '#06b6d4',
  '#ec4899',
  '#84cc16',
  '#6366f1',
  '#f97316',
];

Chart.defaults.font.family =
  'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif';

function useChart(
  type: 'doughnut' | 'bar' | 'line',
  labels: string[],
  values: number[],
  colors: string[],
  horizontal = false,
  cutout = '62%'
) {
  const ref = useRef<HTMLCanvasElement | null>(null);
  const chartRef = useRef<Chart | null>(null);

  useEffect(() => {
    if (!ref.current) return;
    if (chartRef.current) chartRef.current.destroy();

    const isDoughnut = type === 'doughnut';
    const ctx = ref.current.getContext('2d');
    if (!ctx) return;

    chartRef.current = new Chart(ctx, {
      type,
      data: {
        labels,
        datasets: [
          isDoughnut
            ? {
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6,
              }
            : {
                label: '',
                data: values,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1,
                borderRadius: type === 'bar' ? 5 : 0,
                fill: type === 'line' ? true : false,
                tension: type === 'line' ? 0.35 : 0,
                pointRadius: type === 'line' ? 0 : undefined,
                pointHoverRadius: type === 'line' ? 4 : undefined,
              },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: type === 'bar' && horizontal ? 'y' : 'x',
        cutout: isDoughnut ? cutout : undefined,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            titleFont: { size: 12 },
            bodyFont: { size: 12 },
          },
        },
        scales:
          type === 'doughnut'
            ? undefined
            : {
                x: { grid: { display: type === 'line' ? false : true, color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
              },
      },
    });

    return () => {
      if (chartRef.current) chartRef.current.destroy();
      chartRef.current = null;
    };
  }, [type, labels, values, colors, horizontal, cutout]);

  return ref;
}

export function DonutChart({
  labels,
  values,
  colors,
  centerText,
  cutout = '62%',
}: {
  labels: string[];
  values: number[];
  colors?: string[];
  centerText?: string;
  cutout?: string;
}) {
  const palette = colors ?? PALETTE.slice(0, labels.length);
  const ref = useChart('doughnut', labels, values, palette, false, cutout);
  const total = values.reduce((s, v) => s + v, 0);
  return (
    <div className="flex items-center gap-4 py-1 h-full min-h-[150px]">
      <div className="w-32 h-32 relative flex-shrink-0">
        <canvas ref={ref} />
        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span className="text-2xl font-bold text-slate-800 leading-none">{centerText ?? total}</span>
        </div>
      </div>
      <div className="space-y-2 text-xs min-w-0 flex-1">
        {labels.map((l, i) => (
          <div key={l} className="flex items-center gap-2">
            <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: palette[i] }} />
            <span className="text-slate-600 font-medium truncate">{l}</span>
            <span className="ml-auto font-bold text-slate-800 flex-shrink-0">{values[i]}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export function BarChart({
  labels,
  values,
  colors,
  horizontal,
}: {
  labels: string[];
  values: number[];
  colors?: string[];
  horizontal?: boolean;
}) {
  const ref = useChart('bar', labels, values, colors ?? PALETTE.slice(0, labels.length), horizontal);
  return (
    <div className="h-36">
      <canvas ref={ref} />
    </div>
  );
}

export function LineChart({ labels, values }: { labels: string[]; values: number[] }) {
  const ref = useChart('line', labels, values, ['#3b82f6']);
  return (
    <div className="h-40">
      <canvas ref={ref} />
    </div>
  );
}

export function FunnelChart({
  stages,
}: {
  stages: { label: string; value: number; color: string }[];
}) {
  const max = Math.max(1, ...stages.map((s) => s.value));
  return (
    <div className="flex flex-col gap-2.5 py-1">
      {stages.map((s, i) => {
        const pct = Math.round((s.value / max) * 100);
        return (
          <div key={s.label}>
            <div className="flex items-center justify-between text-[11px] mb-1">
              <span className="font-medium text-slate-600">{s.label}</span>
              <span className="font-bold text-slate-800">{s.value}</span>
            </div>
            <div className="w-full h-8 rounded-md overflow-hidden bg-slate-100 relative">
              <div
                className="h-full rounded-md transition-all"
                style={{ width: `${pct}%`, backgroundColor: s.color }}
              />
              <span className="absolute inset-y-0 right-2 flex items-center text-[10px] font-semibold text-slate-500">
                {i === 0 ? '100%' : `${pct}%`}
              </span>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export function NumberCard({
  value,
  sub,
  delta,
  accent,
}: {
  value: number | string;
  sub?: string;
  delta?: { value: number; up: boolean };
  accent?: string;
}) {
  return (
    <div className="h-full flex flex-col justify-center">
      <div className={`text-3xl font-bold text-slate-800 leading-none ${accent ?? ''}`}>{value}</div>
      {sub && <div className="text-[11px] text-slate-500 mt-1.5">{sub}</div>}
      {delta !== undefined && (
        <div
          className={`inline-flex items-center gap-1 text-[11px] font-semibold mt-1 ${
            delta.up ? 'text-emerald-600' : 'text-rose-600'
          }`}
        >
          {delta.up ? <FaArrowTrendUp /> : <FaArrowTrendDown />}
          {delta.value}%
        </div>
      )}
    </div>
  );
}

export function DataTable({
  columns,
  rows,
  onRowClick,
}: {
  columns: string[];
  rows: (string | number)[][];
  onRowClick?: (row: (string | number)[]) => void;
}) {
  return (
    <div className="h-full overflow-auto custom-horizontal-scrollbar">
      <table className="w-full text-left text-[11px]">
        <thead className="bg-slate-50 text-slate-500 font-semibold sticky top-0 z-10 border-b border-slate-200">
          <tr>
            {columns.map((c, i) => (
              <th key={c} className={`py-2 px-3 ${i === 0 ? 'pl-4' : ''} whitespace-nowrap`}>
                {c}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-600">
          {rows.map((row, r) => (
            <tr
              key={r}
              onClick={() => onRowClick?.(row)}
              className={onRowClick ? 'cursor-pointer hover:bg-slate-50 transition' : ''}
            >
              {row.map((cell, c) => (
                <td key={c} className={`py-2 px-3 ${c === 0 ? 'pl-4 font-medium text-slate-800' : ''} whitespace-nowrap`}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function LeadStageList({
  leads,
  onOpenContact,
}: {
  leads: { contactId: number; name: string; phone: string | null; status: DealerLeadStatus }[];
  onOpenContact?: (id: number) => void;
}) {
  return (
    <div className="h-full overflow-auto">
      <table className="w-full text-left text-[11px]">
        <thead className="bg-slate-50 text-slate-500 font-semibold sticky top-0 z-10 border-b border-slate-200">
          <tr>
            <th className="py-2 px-3 pl-4">Lead</th>
            <th className="py-2 px-3">Stage</th>
            <th className="py-2 px-3 text-right pr-4">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-600">
          {leads.map((l) => (
            <tr key={l.contactId} className={onOpenContact ? 'cursor-pointer hover:bg-slate-50 transition' : ''}>
              <td className="py-2 px-3 pl-4">
                <div className="font-medium text-slate-800 truncate">{l.name}</div>
                {l.phone && <div className="text-[10px] text-slate-400">{l.phone}</div>}
              </td>
              <td className="py-2 px-3">
                <span
                  className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full font-semibold"
                  style={{ backgroundColor: `${STATUS_COLORS[l.status]}1a`, color: STATUS_COLORS[l.status] }}
                >
                  <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: STATUS_COLORS[l.status] }} />
                  {STATUS_META[l.status].label}
                </span>
              </td>
              <td className="py-2 px-3 text-right pr-4">
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    onOpenContact?.(l.contactId);
                  }}
                  className="text-[10px] text-blue-600 hover:underline font-semibold inline-flex items-center gap-1"
                >
                  <FaPhone className="text-[9px]" /> Open
                </button>
              </td>
            </tr>
          ))}
          {leads.length === 0 && (
            <tr>
              <td colSpan={3} className="py-6 text-center text-[11px] text-slate-400">
                No leads assigned yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}