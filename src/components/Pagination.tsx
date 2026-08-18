interface PaginationProps {
  currentPage: number;
  totalPages: number;
}

function Pagination({ currentPage, totalPages }: PaginationProps) {
  const isPrevDisabled = currentPage <= 1;
  const isNextDisabled = currentPage >= totalPages;

  const btnClass = (disabled: boolean) =>
    disabled
      ? 'h-8 px-3 border border-slate-200 rounded-md text-slate-400 bg-slate-50 text-xs font-medium cursor-not-allowed'
      : 'h-8 px-3 border border-slate-200 rounded-md text-slate-700 hover:bg-slate-50 text-xs font-medium transition';

  return (
    <footer className="px-4 md:px-6 py-2 border-t border-slate-200 bg-white flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 flex-shrink-0 gap-2">
      <div className="font-medium whitespace-nowrap">
        Page <span>{currentPage}</span> of <span>{totalPages}</span>
      </div>

      <div className="flex items-center space-x-3">
        <div className="flex items-center space-x-1">
          <select
            defaultValue="20"
            className="h-8 border border-slate-200 rounded-md px-2 bg-white text-xs font-medium focus:outline-none focus:border-slate-400 cursor-pointer"
            aria-label="Items per page"
          >
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <div className="flex items-center space-x-1">
          <button disabled={isPrevDisabled} className={btnClass(isPrevDisabled)}>
            Prev
          </button>
          <button className="h-8 px-3 border border-blue-500 bg-white text-blue-600 rounded-md text-xs font-semibold shadow-sm">
            {currentPage}
          </button>
          <button disabled={isNextDisabled} className={btnClass(isNextDisabled)}>
            Next
          </button>
        </div>
      </div>
    </footer>
  );
}

export default Pagination;
