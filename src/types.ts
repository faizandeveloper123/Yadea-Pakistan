export interface Contact {
  id: number;
  name: string;
  initials: string;
  avatarColor: string;
  image?: string;
  phone?: string;
  email?: string;
  businessName?: string;
  contactType?: string;
  owner?: string;
  ownerAvatar?: string;
  assignedTo?: number | null;
  createdPkt?: string;
  lastActivityPkt?: string;
  updatedPkt?: string;
  sortCreated?: string;
  sortActivity?: string;
  sortUpdated?: string;
  tags?: string[];
  customFields?: Record<string, unknown>;
  followers?: { id: number; first_name: string; last_name: string; full_name: string; avatar_data?: string | null }[];
  tagExtraCount?: number;
  isHighlighted?: boolean;
}

export interface ImportedContactInput {
  name: string;
  phone: string;
  email: string;
  business: string;
  tag: string;
  color: string;
}
