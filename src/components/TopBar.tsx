import {
  FaBars,
  FaBullhorn,
  FaPhone,
  FaQuestion,
  FaWandMagicSparkles,
} from 'react-icons/fa6';
import type { ApiStaffUser } from '../api';
import NotificationsBell from './NotificationsBell';
import UserMenu from './UserMenu';

export const topTabs = [
  'Smart Lists',
  'Bulk Actions',
  'Custom Fields',
  'Tasks',
  'Companies',
];

interface TopBarProps {
  activeTab: string;
  onSelectTab: (tab: string) => void;
  onOpenMobileSidebar: () => void;
  user: ApiStaffUser | null;
  onLogout?: () => void;
}

function TopBar({ activeTab, onSelectTab, onOpenMobileSidebar, user, onLogout }: TopBarProps) {
  return (
    <header className="border-b border-slate-200 px-3 md:px-6 flex items-center justify-between bg-white flex-shrink-0 gap-2 overflow-visible">
      <div className="flex items-center space-x-3 min-w-0">
        <button
          onClick={onOpenMobileSidebar}
          className="md:hidden p-2 text-slate-600 hover:text-slate-900 focus:outline-none flex-shrink-0"
          aria-label="Open sidebar menu"
        >
          <FaBars className="text-lg" />
        </button>

        <nav className="flex space-x-4 md:space-x-6 h-full items-center text-xs md:text-sm font-medium overflow-x-auto whitespace-nowrap py-2 no-scrollbar">
          {/* Contacts is a page heading, NOT a clickable tab */}
          <span className="text-slate-800 border-b-2 border-blue-600 h-full flex items-center px-1 font-semibold select-none">
            Contacts
          </span>
          {topTabs.map((tab) => {
            const isActive = activeTab === tab;
            return (
              <button
                key={tab}
                onClick={() => onSelectTab(tab)}
                aria-current={isActive ? 'page' : undefined}
                className={
                  isActive
                    ? 'text-slate-800 border-b-2 border-blue-600 h-full flex items-center px-1 font-semibold'
                    : 'text-slate-500 hover:text-slate-800 h-full flex items-center px-1'
                }
              >
                {tab}
              </button>
            );
          })}
        </nav>
      </div>

      <div className="flex items-center space-x-1.5 md:space-x-2 flex-shrink-0">
        <div className="hidden lg:flex items-center bg-blue-50 hover:bg-blue-100/80 rounded-full text-xs text-blue-700 pl-2 pr-1 py-1 cursor-pointer transition border border-blue-100 space-x-1.5">
          <FaBullhorn className="text-blue-600 text-xs" />
          <span>What's new</span>
          <span className="bg-blue-600 text-white text-[11px] px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
            Contact updates
            <span className="w-1.5 h-1.5 bg-red-400 rounded-full inline-block" />
          </span>
        </div>

        <button
          className="w-7 h-7 md:w-8 md:h-8 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center text-xs shadow-sm transition"
          aria-label="Call"
        >
          <FaPhone />
        </button>

        <button className="bg-indigo-700 hover:bg-indigo-800 text-white text-xs px-2.5 md:px-3 py-1.5 rounded-full font-medium flex items-center space-x-1.5 shadow-sm transition">
          <FaWandMagicSparkles className="text-xs" />
          <span className="hidden sm:inline">Ask AI</span>
        </button>

        <button
          className="w-7 h-7 md:w-8 md:h-8 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center text-xs shadow-sm transition hidden sm:flex"
          aria-label="Campaigns"
        >
          <FaBullhorn />
        </button>

        <NotificationsBell />

        <button
          className="w-7 h-7 md:w-8 md:h-8 rounded-full bg-sky-500 hover:bg-sky-600 text-white flex items-center justify-center text-xs shadow-sm transition hidden sm:flex"
          aria-label="Help"
        >
          <FaQuestion />
        </button>

        <div className="flex items-center gap-1.5 pl-1 border-l border-slate-100 ml-1">
          {user && <UserMenu user={user} onLogout={onLogout} />}
        </div>
      </div>
    </header>
  );
}

export default TopBar;