import { useState } from 'react';
import type { Contact } from '../types';
import { fieldById } from '../data/tableFields';
import { useFollowers, useOwners } from '../data/followersStore';
import {
  FaArrowsUpDown,
  FaEllipsisVertical,
  FaPeopleArrows,
  FaRegCalendarCheck,
  FaRegTrashCan,
  FaStar,
} from 'react-icons/fa6';

export type RowActionId = 'book' | 'opportunity' | 'review' | 'delete';

interface ContactsTableProps {
  contacts: Contact[];
  selectedIds: Set<number>;
  onToggleSelect: (id: number) => void;
  onToggleSelectAll: () => void;
  allVisibleSelected: boolean;
  someVisibleSelected: boolean;
  onOpenContact: (id: number) => void;
  visibleFields: string[];
  onRowAction: (action: RowActionId, contact: Contact) => void;
  canDelete?: boolean;
}

function RowActionsMenu({
  contact,
  onAction,
  canDelete = true,
}: {
  contact: Contact;
  onAction: (action: RowActionId, contact: Contact) => void;
  canDelete?: boolean;
}) {
  const [open, setOpen] = useState(false);

  const items: { id: RowActionId; label: string; icon: React.ReactNode; danger?: boolean }[] = [
    { id: 'book', label: 'Book appointment', icon: <FaRegCalendarCheck className="text-slate-400" /> },
    { id: 'opportunity', label: 'Create opportunity', icon: <FaPeopleArrows className="text-slate-400" /> },
    { id: 'review', label: 'Send review request', icon: <FaStar className="text-slate-400" /> },
    ...(canDelete
      ? [{ id: 'delete' as RowActionId, label: 'Delete Contact', icon: <FaRegTrashCan className="text-red-500" />, danger: true }]
      : []),
  ];

  return (
    <div className="relative flex-shrink-0">
      <button
        onClick={(e) => {
          e.stopPropagation();
          setOpen((v) => !v);
        }}
        title="Actions"
        className={`w-6 h-6 rounded-md flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition ${
          open ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'
        }`}
      >
        <FaEllipsisVertical className="text-xs" />
      </button>
      {open && (
        <>
          <div
            className="fixed inset-0 z-20"
            onClick={(e) => {
              e.stopPropagation();
              setOpen(false);
            }}
          />
          <div className="absolute right-0 top-7 z-30 bg-white border border-slate-200 rounded-lg shadow-xl py-1 w-56 text-xs">
            {items.map((item) => (
              <button
                key={item.id}
                onClick={(e) => {
                  e.stopPropagation();
                  setOpen(false);
                  onAction(item.id, contact);
                }}
                className={`w-full text-left px-3 py-2 hover:bg-slate-50 flex items-center gap-2.5 transition ${
                  item.danger ? 'text-red-600 font-medium' : 'text-slate-700'
                }`}
              >
                <span className="flex-shrink-0">{item.icon}</span>
                <span>{item.label}</span>
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

function ContactsTable({
  contacts,
  selectedIds,
  onToggleSelect,
  onToggleSelectAll,
  allVisibleSelected,
  someVisibleSelected,
  onOpenContact,
  visibleFields,
  onRowAction,
  canDelete = true,
}: ContactsTableProps) {
  const cols = visibleFields.map((id) => fieldById(id)).filter((f) => !!f);
  const liveFollowers = useFollowers();
  const liveOwners = useOwners();

  const rows = contacts.map((contact) => {
    let updated = contact;
    const live = liveFollowers[contact.id];
    if (live && !(updated.followers && updated.followers.length === live.length && updated.followers.every((f) => live.some((lf) => lf.id === f.id)))) {
      updated = { ...updated, followers: live };
    }
    const owner = liveOwners[contact.id];
    if (owner) {
      updated = {
        ...updated,
        owner: owner.ownerName ?? undefined,
        ownerAvatar: owner.ownerAvatar ?? undefined,
        assignedTo: owner.assignedTo,
      };
    }
    return updated;
  });

  return (
    <div className="flex-1 overflow-auto custom-horizontal-scrollbar relative">
      <table className="w-full text-left border-collapse text-xs min-w-[1100px]">
        <thead className="bg-slate-50 text-slate-600 font-semibold sticky top-0 z-10 border-b border-slate-200">
          <tr>
            <th className="py-2.5 px-3 w-10 text-center">
              <input
                type="checkbox"
                checked={allVisibleSelected}
                ref={(el) => {
                  if (el) el.indeterminate = someVisibleSelected && !allVisibleSelected;
                }}
                onChange={onToggleSelectAll}
                aria-label="Select all rows"
              />
            </th>
            {cols.map((col, idx) => (
              <th
                key={col.id}
                className={
                  idx === cols.length - 1
                    ? 'py-2.5 px-3 font-semibold text-slate-700'
                    : 'py-2.5 px-3 font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer'
                }
              >
                <div className="flex items-center justify-between">
                  <span>{col.label}</span>
                  {idx !== cols.length - 1 && <FaArrowsUpDown className="text-[10px] text-slate-400" />}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-700">
          {rows.map((contact) => {
            const isSelected = selectedIds.has(contact.id);
            return (
              <tr
                key={contact.id}
                onClick={() => onOpenContact(contact.id)}
                className={`group hover:bg-slate-50 transition border-b border-slate-100 cursor-pointer ${
                  contact.isHighlighted ? 'bg-blue-50/20' : ''
                }`}
              >
                <td className="py-1.5 px-3 text-center" onClick={(e) => e.stopPropagation()}>
                  <input
                    type="checkbox"
                    className="row-checkbox"
                    checked={isSelected}
                    onChange={() => onToggleSelect(contact.id)}
                    aria-label={`Select ${contact.name}`}
                  />
                </td>
                {cols.map((col) => (
                  <td key={col.id} className="py-1.5 px-3 text-slate-600">
                    {col.id === 'contact_name' ? (
                      <div className="flex items-center justify-between gap-2 pr-1">
                        <div className="min-w-0 flex-1">{col.render(contact)}</div>
                        <RowActionsMenu contact={contact} onAction={onRowAction} canDelete={canDelete} />
                      </div>
                    ) : (
                      col.render(contact)
                    )}
                  </td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default ContactsTable;
