import app from 'flarum/common/app';

export interface ProjectCategory {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  color?: string | null;
}

export interface CategoryDef extends ProjectCategory {
  description?: string | null;
  position: number;
}

export type FieldType = 'text' | 'textarea' | 'number' | 'date' | 'url' | 'select' | 'boolean';

export interface FieldDef {
  id: number;
  name: string;
  key: string;
  type: FieldType;
  options: string[];
  icon?: string | null;
  prefix?: string | null;
  suffix?: string | null;
  isRequired: boolean;
  onCard: boolean;
  position: number;
}

export interface ButtonDef {
  id: number;
  label: string;
  key: string;
  icon?: string | null;
  allowedDomains: string[];
  allowCustomLabel: boolean;
  isRequired: boolean;
  isPrimary: boolean;
  position: number;
}

export interface ProjectFieldValue {
  id: number;
  key: string;
  name: string;
  type: FieldType;
  icon?: string | null;
  prefix?: string | null;
  suffix?: string | null;
  onCard: boolean;
  value: string;
}

export interface ProjectLink {
  id: number;
  buttonId: number | null;
  url: string;
  label: string;
  icon?: string | null;
  isPrimary: boolean;
}

export interface ProjectAuthor {
  id: number;
  username: string;
  displayName: string;
  avatarUrl: string | null;
  slug: string;
}

export interface Project {
  id: number;
  title: string;
  slug: string;
  excerpt?: string | null;
  image?: string | null;
  status: 'pending' | 'published' | 'rejected';
  rejectionReason?: string | null;
  likesCount: number;
  liked: boolean | null;
  createdAt?: string;
  updatedAt?: string;
  author: ProjectAuthor | null;
  primaryCategory: ProjectCategory | null;
  categories: ProjectCategory[];
  fields: ProjectFieldValue[];
  links: ProjectLink[];
  discussionId: number | null;
  canEdit: boolean;
  canDelete: boolean;
  canModerate: boolean;
  contentHtml?: string;
  content?: string | null;
}

export interface ProjectsConfig {
  categories: CategoryDef[];
  fields: FieldDef[];
  buttons: ButtonDef[];
}

export interface ListParams {
  q?: string;
  category?: string;
  user?: number;
  status?: string;
  sort?: 'recent' | 'popular' | 'title';
  page?: number;
  perPage?: number;
}

export interface ListResult {
  data: Project[];
  meta: { total: number; page: number; perPage: number; hasMore: boolean };
}

function base(): string {
  return app.forum.attribute<string>('apiUrl') + '/projects';
}

/** Admin-defined building blocks shipped in the forum boot payload. */
export function config(): ProjectsConfig {
  return (
    app.forum.attribute<ProjectsConfig>('projectsConfig') || { categories: [], fields: [], buttons: [] }
  );
}

export function listProjects(params: ListParams = {}): Promise<ListResult> {
  return app.request<ListResult>({
    method: 'GET',
    url: base(),
    params,
  });
}

export function getProject(idOrSlug: number | string): Promise<{ data: Project }> {
  return app.request<{ data: Project }>({
    method: 'GET',
    url: `${base()}/${idOrSlug}`,
  });
}

export function saveProject(attributes: Record<string, unknown>, id?: number): Promise<{ data: Project }> {
  return app.request<{ data: Project }>({
    method: id ? 'PATCH' : 'POST',
    url: id ? `${base()}/${id}` : base(),
    body: { data: { attributes } },
  });
}

export function deleteProject(id: number): Promise<void> {
  return app.request({ method: 'DELETE', url: `${base()}/${id}` });
}

export function likeProject(id: number): Promise<{ data: Project }> {
  return app.request<{ data: Project }>({ method: 'POST', url: `${base()}/${id}/like` });
}

export function moderateProject(id: number, action: 'approve' | 'reject', reason?: string): Promise<{ data: Project }> {
  return app.request<{ data: Project }>({
    method: 'POST',
    url: `${base()}/${id}/moderate`,
    body: { data: { attributes: { action, reason } } },
  });
}

/** Upload a project image; resolves to its public URL. */
export function uploadImage(file: File): Promise<string> {
  const body = new FormData();
  body.append('image', file);

  return app
    .request<{ data: { url: string } }>({
      method: 'POST',
      url: `${base()}/upload-image`,
      serialize: (raw: unknown) => raw, // send the FormData untouched
      body,
    })
    .then((res) => res.data.url);
}

// ---- Admin: definition management ----------------------------------------

export function getConfig(): Promise<{ data: ProjectsConfig }> {
  return app.request<{ data: ProjectsConfig }>({ method: 'GET', url: `${base()}/config` });
}

function saveDefinition<T>(kind: string, attributes: Record<string, unknown>, id?: number): Promise<{ data: T }> {
  return app.request<{ data: T }>({
    method: id ? 'PATCH' : 'POST',
    url: id ? `${base()}/config/${kind}/${id}` : `${base()}/config/${kind}`,
    body: { data: { attributes } },
  });
}

function deleteDefinition(kind: string, id: number): Promise<void> {
  return app.request({ method: 'DELETE', url: `${base()}/config/${kind}/${id}` });
}

export const saveCategory = (a: Record<string, unknown>, id?: number) => saveDefinition<CategoryDef>('categories', a, id);
export const deleteCategory = (id: number) => deleteDefinition('categories', id);
export const saveField = (a: Record<string, unknown>, id?: number) => saveDefinition<FieldDef>('fields', a, id);
export const deleteField = (id: number) => deleteDefinition('fields', id);
export const saveButton = (a: Record<string, unknown>, id?: number) => saveDefinition<ButtonDef>('buttons', a, id);
export const deleteButton = (id: number) => deleteDefinition('buttons', id);
