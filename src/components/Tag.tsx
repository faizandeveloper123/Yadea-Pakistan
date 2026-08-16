interface TagProps {
  label: string;
}

function Tag({ label }: TagProps) {
  return (
    <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] border border-slate-200 whitespace-nowrap">
      {label}
    </span>
  );
}

export default Tag;
