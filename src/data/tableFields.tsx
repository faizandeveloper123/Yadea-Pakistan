import type { ReactNode } from 'react';
import type { Contact } from '../types';
import type { FilterGroup } from './smartListOptions';
import Avatar from '../components/Avatar';
import Tag from '../components/Tag';
import { FaEnvelope, FaPhone } from 'react-icons/fa6';

export interface TableField {
  id: string;
  label: string;
  group: string | null;
  locked?: boolean;
  render: (c: Contact) => ReactNode;
}

export interface FieldGroup {
  id: string;
  label: string;
}

export const FIELD_GROUPS: FieldGroup[] = [
  { id: 'contact', label: 'Contact' },
  { id: 'general', label: 'General Info' },
  { id: 'additional', label: 'Additional Info' },
  { id: 'form', label: 'Form | Auto Dealer Contact Us' },
  { id: 'activity', label: 'Contact activity' },
];

const firstOf = (c: Contact) => c.name.split(' ').filter(Boolean)[0] ?? '';
const lastOf = (c: Contact) => {
  const parts = c.name.split(' ').filter(Boolean);
  return parts.length > 1 ? parts[parts.length - 1] : '';
};

export const TABLE_FIELDS: Record<string, TableField> = {
  contact_name: {
    id: 'contact_name',
    label: 'Contact name',
    group: null,
    locked: true,
    render: (c) => {
      const list = c.followers ?? [];
      return (
        <div className="flex items-center space-x-2">
          <Avatar initials={c.initials} color={c.avatarColor} image={c.image} size="w-6 h-6" />
          <div className="min-w-0 flex items-center gap-1.5">
            <span className="font-medium text-slate-800 group-hover:text-blue-600 truncate text-[13px]">{c.name}</span>
            {c.ownerAvatar && (
              <img
                src={c.ownerAvatar}
                alt={c.owner}
                title={`Owner: ${c.owner}`}
                className="w-5 h-5 rounded-full object-cover border-2 border-indigo-200 shadow-sm flex-shrink-0"
              />
            )}
            {list.length > 0 && (
              <span className="flex items-center gap-0.5 flex-shrink-0">
                {list.map((f) =>
                  f.avatar_data ? (
                    <img
                      key={f.id}
                      src={f.avatar_data}
                      alt={f.full_name}
                      title={f.full_name}
                      className="w-5 h-5 rounded-full object-cover border border-white shadow-sm flex-shrink-0"
                    />
                  ) : (
                    <span
                      key={f.id}
                      title={f.full_name}
                      className="w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[8px] font-bold flex items-center justify-center flex-shrink-0"
                    >
                      {(f.first_name[0] ?? '').toUpperCase()}
                      {(f.last_name[0] ?? '').toUpperCase()}
                    </span>
                  )
                )}
              </span>
            )}
          </div>
        </div>
      );
    },
  },
  phone: {
    id: 'phone',
    label: 'Phone',
    group: 'contact',
    render: (c) =>
      c.phone ? (
        <div className="flex items-center space-x-1.5">
          <FaPhone className="text-slate-400 text-[10px]" />
          <span>{c.phone}</span>
        </div>
      ) : (
        ''
      ),
  },
  email: {
    id: 'email',
    label: 'Email',
    group: 'contact',
    render: (c) =>
      c.email ? (
        <div className="flex items-center space-x-1.5">
          <FaEnvelope className="text-slate-400 text-[10px]" />
          <span>{c.email}</span>
        </div>
      ) : (
        ''
      ),
  },
  business_name: {
    id: 'business_name',
    label: 'Business name',
    group: 'general',
    render: (c) => c.businessName ?? '',
  },
  created: {
    id: 'created',
    label: 'Created (PKT)',
    group: 'contact',
    render: (c) => <span className="whitespace-nowrap">{c.createdPkt ?? ''}</span>,
  },
  last_activity: {
    id: 'last_activity',
    label: 'Last activity (PKT)',
    group: 'activity',
    render: (c) => c.lastActivityPkt ?? '',
  },
  tags: {
    id: 'tags',
    label: 'Tags',
    group: 'contact',
    render: (c) => (
      <div className="flex items-center space-x-1">
        {(c.tags ?? []).map((tag) => (
          <Tag key={tag} label={tag} />
        ))}
        {c.tagExtraCount !== undefined && (
          <span className="px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-medium border border-slate-200">
            +{c.tagExtraCount}
          </span>
        )}
      </div>
    ),
  },
  first_name: { id: 'first_name', label: 'First name', group: 'contact', render: firstOf },
  last_name: { id: 'last_name', label: 'Last name', group: 'contact', render: lastOf },
  date_of_birth: { id: 'date_of_birth', label: 'Date of birth', group: 'contact', render: () => '' },
  contact_source: { id: 'contact_source', label: 'Contact source', group: 'contact', render: () => '' },
  contact_type: {
    id: 'contact_type',
    label: 'Contact type',
    group: 'contact',
    render: (c) =>
      c.contactType ? (
        <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
          {c.contactType}
        </span>
      ) : (
        ''
      ),
  },
  dnd: { id: 'dnd', label: 'DND', group: 'contact', render: () => '' },
  additional_emails: { id: 'additional_emails', label: 'Additional emails', group: 'contact', render: () => '' },
  additional_phones: { id: 'additional_phones', label: 'Additional phones', group: 'contact', render: () => '' },
  updated: { id: 'updated', label: 'Updated (PKT)', group: 'contact', render: (c) => c.updatedPkt ?? '' },
  followers: {
    id: 'followers',
    label: 'Followers',
    group: 'contact',
    render: (c) => {
      const list = c.followers ?? [];
      if (list.length === 0) return '';
      return (
        <div className="flex items-center gap-1 flex-wrap">
          {list.map((f) =>
            f.avatar_data ? (
              <img
                key={f.id}
                src={f.avatar_data}
                alt={f.full_name}
                title={f.full_name}
                className="w-5 h-5 rounded-full object-cover border border-white shadow-sm flex-shrink-0"
              />
            ) : (
              <span
                key={f.id}
                title={f.full_name}
                className="w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold flex items-center justify-center flex-shrink-0"
              >
                {(f.first_name[0] ?? '').toUpperCase()}
                {(f.last_name[0] ?? '').toUpperCase()}
              </span>
            )
          )}
        </div>
      );
    },
  },
  owner: {
    id: 'owner',
    label: 'Owner',
    group: 'contact',
    render: (c) =>
      c.owner ? (
        <div className="flex items-center gap-1.5">
          {c.ownerAvatar ? (
            <img
              src={c.ownerAvatar}
              alt={c.owner}
              title={c.owner}
              className="w-5 h-5 rounded-full object-cover border border-white shadow-sm flex-shrink-0"
            />
          ) : (
            <span className="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 text-[8px] font-bold flex items-center justify-center flex-shrink-0">
              {(c.owner[0] ?? '').toUpperCase()}
            </span>
          )}
          <span className="text-slate-600">{c.owner}</span>
        </div>
      ) : (
        ''
      ),
  },
  valid_whatsapp: { id: 'valid_whatsapp', label: 'Valid WhatsApp', group: 'contact', render: () => '' },
  street_address: { id: 'street_address', label: 'Street address', group: 'general', render: () => '' },
  city: { id: 'city', label: 'City', group: 'general', render: () => '' },
  country: { id: 'country', label: 'Country', group: 'general', render: () => '' },
  state: { id: 'state', label: 'State', group: 'general', render: () => '' },
  postal_code: { id: 'postal_code', label: 'Postal code', group: 'general', render: () => '' },
  website: { id: 'website', label: 'Website', group: 'general', render: () => '' },
  timezone: { id: 'timezone', label: 'Timezone', group: 'general', render: () => '' },
  company_name: { id: 'company_name', label: 'Company name', group: 'general', render: () => '' },
  full_address: { id: 'full_address', label: 'Full address', group: 'general', render: () => '' },
  flooring_interest: {
    id: 'flooring_interest',
    label: 'What Type of Flooring Are You Interested In?',
    group: 'additional',
    render: () => '',
  },
  project_size: {
    id: 'project_size',
    label: 'Approximate Project Size',
    group: 'additional',
    render: () => '',
  },
  start_time: {
    id: 'start_time',
    label: 'When Would You Like to Start?',
    group: 'additional',
    render: () => '',
  },
  additional_project_details: {
    id: 'additional_project_details',
    label: 'Additional Project Details',
    group: 'additional',
    render: () => '',
  },
  flooring_type: {
    id: 'flooring_type',
    label: 'Project Flooring Type',
    group: 'additional',
    render: () => '',
  },
  bike_model: {
    id: 'bike_model',
    label: 'Model of The Bike-Evee',
    group: 'form',
    render: () => '',
  },
  workflows_active: { id: 'workflows_active', label: 'Workflows active', group: 'activity', render: () => '' },
  workflows_finished: { id: 'workflows_finished', label: 'Workflows finished', group: 'activity', render: () => '' },
  opportunities: { id: 'opportunities', label: 'Opportunities', group: 'activity', render: () => '' },
  offers: { id: 'offers', label: 'Offers', group: 'activity', render: () => '' },
  products: { id: 'products', label: 'Products', group: 'activity', render: () => '' },
  last_appointment: {
    id: 'last_appointment',
    label: 'Last appointment - confirmed/open (PKT)',
    group: 'activity',
    render: () => '',
  },
  last_note: { id: 'last_note', label: 'Last note', group: 'activity', render: () => '' },
};

export const DEFAULT_VISIBLE_FIELDS = [
  'contact_name',
  'phone',
  'email',
  'business_name',
  'created',
  'last_activity',
  'tags',
];

export function fieldById(id: string): TableField | undefined {
  return TABLE_FIELDS[id];
}

export function fieldsInGroup(groupId: string): TableField[] {
  return Object.values(TABLE_FIELDS).filter((f) => f.group === groupId);
}

export const TABLE_FILTER_GROUPS: FilterGroup[] = FIELD_GROUPS.map((g) => ({
  id: g.id,
  label: g.label,
  options: fieldsInGroup(g.id).map((f) => f.label),
}));
