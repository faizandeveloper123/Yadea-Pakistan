import { FaArrowsUpDown, FaBarsStaggered, FaFilter, FaGear, FaMagnifyingGlass } from 'react-icons/fa6';

interface ToolbarProps {
  searchQuery: string;
  onSearchChange: (query: string) => void;
  selectedCount: number;
  onDeleteSelected: () => void;
  onManageFields: () => void;
  onOpenFilters: () => void;
  onOpenSort: () => void;
  filterCount: number;
  sortBy: string;
  canDelete?: boolean;
  onAddToList?: () => void;
}

function Toolbar({
  searchQuery,
  onSearchChange,
  selectedCount,
  onDeleteSelected,
  onManageFields,
  onOpenFilters,
  onOpenSort,
  filterCount,
  sortBy,
  canDelete = true,
  onAddToList,
}: ToolbarProps) {
  return (
    <div className="px-4 md:px-6 py-2 flex flex-col sm:flex-row items-center justify-between border-b border-slate-100 bg-white flex-shrink-0 gap-2">
      <div className="flex items-center space-x-2 overflow-x-auto pb-1 sm:pb-0">
        <button
          onClick={onOpenFilters}
          className="px-3 py-1.5 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-md text-xs font-medium flex items-center space-x-1.5 transition whitespace-nowrap"
        >
          <FaFilter className="text-slate-500" />
          <span>Filters</span>
          {filterCount > 0 && (
            <span className="bg-blue-600 text-white rounded-full px-1.5 text-[10px] font-bold leading-4">
              {filterCount}
            </span>
          )}
        </button>

        <button
          onClick={onOpenSort}
          className="px-3 py-1.5 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-md text-xs font-medium flex items-center space-x-1.5 transition whitespace-nowrap"
        >
          <FaArrowsUpDown className="text-slate-500" />
          <span>Sort</span>
          {sortBy && <span className="text-blue-600 text-[10px] font-semibold max-w-[110px] truncate">{sortBy}</span>}
        </button>

        {selectedCount > 0 && (
          <div className="flex items-center space-x-2 bg-blue-50 text-blue-700 px-3 py-1 rounded-md text-xs whitespace-nowrap">
            <span className="font-semibold">{selectedCount} selected</span>
            {onAddToList && (
              <button
                onClick={onAddToList}
                className="inline-flex items-center gap-1 text-blue-700 hover:text-blue-900 font-medium ml-2 transition"
              >
                <FaBarsStaggered className="text-[10px]" />
                Add to List
              </button>
            )}
            {canDelete && (
              <button onClick={onDeleteSelected} className="text-red-600 hover:text-red-800 font-medium ml-2">
                Delete
              </button>
            )}
          </div>
        )}
      </div>

      <div className="flex items-center space-x-3 justify-between sm:justify-end">
        <div className="relative flex-1 sm:flex-initial">
          <FaMagnifyingGlass className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder="Search Contacts"
            className="w-full sm:w-64 border border-slate-300 rounded-md pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            aria-label="Search Contacts"
          />
        </div>

        <button
          onClick={onManageFields}
          className="text-slate-700 hover:text-slate-900 text-xs font-medium flex items-center space-x-1.5 whitespace-nowrap flex-shrink-0"
        >
          <FaGear className="text-slate-500" />
          <span className="hidden sm:inline">Manage fields</span>
        </button>
      </div>
    </div>
  );
}

export default Toolbar;
