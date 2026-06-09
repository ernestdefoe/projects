import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import { config, getProject, saveProject, uploadImage, type ButtonDef, type FieldDef, type Project } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

/**
 * Create / edit a project. The form is driven entirely by the admin-defined
 * config (categories, custom fields, button slots) shipped in the boot payload,
 * so it adapts to whatever a community has set up.
 */
export default class ProjectFormModal extends Modal {
  cfg = config();
  editing: Project | null = null;

  // form state
  titleText = '';
  excerpt = '';
  contentText = '';
  image: string | null = null;
  categoryIds: number[] = [];
  primaryCategoryId: number | null = null;
  fieldValues: Record<number, string> = {};
  links: Record<number, { url: string; label: string }> = {};
  discussionId = '';

  uploading = false;
  loading = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    const existing: Project | undefined = this.attrs.project;
    if (existing) {
      // Fetch the full record (with raw content + relations) before editing.
      this.loading = true;
      getProject(existing.id).then((res) => {
        this.prefill(res.data);
        this.loading = false;
        m.redraw();
      });
    }
  }

  prefill(p: Project) {
    this.editing = p;
    this.titleText = p.title;
    this.excerpt = p.excerpt || '';
    this.contentText = p.content || '';
    this.image = p.image || null;
    this.categoryIds = p.categories.map((c) => c.id);
    this.primaryCategoryId = p.primaryCategory?.id || null;
    this.discussionId = p.discussionId ? String(p.discussionId) : '';
    p.fields.forEach((f) => (this.fieldValues[f.id] = f.value));
    p.links.forEach((l) => {
      if (l.buttonId) this.links[l.buttonId] = { url: l.url, label: l.label || '' };
    });
  }

  className() {
    return 'ProjectFormModal Modal--large';
  }

  title() {
    return this.editing ? t('form.edit_title') : t('form.create_title');
  }

  content() {
    if (this.loading) {
      return m('.Modal-body.ProjectForm-loading', m('span.LoadingIndicator'));
    }

    return m('.Modal-body', [
      m('.Form-group', [
        m('label', t('form.title_label')),
        m('input.FormControl', { value: this.titleText, oninput: (e: any) => (this.titleText = e.target.value), maxlength: 255 }),
      ]),

      m('.Form-group', [
        m('label', t('form.image_label')),
        m('.ProjectForm-image', [
          this.image ? m('img.ProjectForm-imagePreview', { src: this.image }) : null,
          m('label.Button', [
            this.uploading ? m('span.LoadingIndicator', { className: 'LoadingIndicator--inline' }) : m('i.fas.fa-upload'),
            ' ',
            this.image ? t('form.image_change') : t('form.image_upload'),
            m('input', {
              type: 'file',
              accept: 'image/*',
              style: 'display:none',
              onchange: (e: any) => this.upload(e.target.files[0]),
            }),
          ]),
          this.image ? Button.component({ className: 'Button Button--text', onclick: () => (this.image = null) }, t('form.image_remove')) : null,
        ]),
      ]),

      m('.Form-group', [
        m('label', t('form.excerpt_label')),
        m('textarea.FormControl', {
          rows: 2,
          maxlength: app.forum.attribute('projectsExcerptLimit') || 280,
          value: this.excerpt,
          oninput: (e: any) => (this.excerpt = e.target.value),
        }),
        m('span.helpText', t('form.excerpt_help', { count: (app.forum.attribute('projectsExcerptLimit') || 280) - this.excerpt.length })),
      ]),

      m('.Form-group', [
        m('label', t('form.content_label')),
        m('textarea.FormControl', { rows: 6, value: this.contentText, oninput: (e: any) => (this.contentText = e.target.value) }),
      ]),

      this.cfg.categories.length ? this.categorySection() : null,
      this.cfg.fields.length ? this.fieldsSection() : null,
      this.cfg.buttons.length ? this.buttonsSection() : null,

      m('.Form-group', [
        m('label', t('form.discussion_label')),
        m('input.FormControl', {
          type: 'number',
          placeholder: t('form.discussion_placeholder'),
          value: this.discussionId,
          oninput: (e: any) => (this.discussionId = e.target.value),
        }),
      ]),

      m('.Form-group', Button.component({ className: 'Button Button--primary Button--block', loading: this.loading, onclick: () => this.submit() }, this.editing ? t('form.save') : t('form.create'))),
    ]);
  }

  categorySection() {
    return m('.Form-group.ProjectForm-categories', [
      m('label', t('form.categories_label')),
      m('.ProjectForm-catChips', this.cfg.categories.map((c) => {
        const on = this.categoryIds.includes(c.id);
        return m('button.ProjectForm-catChip' + (on ? '.is-on' : ''), {
          type: 'button',
          style: c.color ? { '--project-accent': c.color } : undefined,
          onclick: () => this.toggleCategory(c.id),
        }, [c.icon ? m('i', { className: c.icon }) : null, ' ', c.name]);
      })),
      this.categoryIds.length > 1
        ? m('.ProjectForm-primary', [
            m('label', t('form.primary_label')),
            m('select.FormControl', {
              value: String(this.primaryCategoryId || ''),
              onchange: (e: any) => (this.primaryCategoryId = Number(e.target.value) || null),
            }, this.categoryIds.map((id) => {
              const c = this.cfg.categories.find((x) => x.id === id);
              return c ? m('option', { value: String(id) }, c.name) : null;
            })),
          ])
        : null,
    ]);
  }

  fieldsSection() {
    return m('.ProjectForm-fields', this.cfg.fields.map((f) => this.fieldInput(f)));
  }

  fieldInput(f: FieldDef) {
    const val = this.fieldValues[f.id] ?? '';
    const set = (v: string) => (this.fieldValues[f.id] = v);
    const label = m('label', [f.icon ? m('i', { className: f.icon }) : null, ' ', f.name, f.isRequired ? m('span.ProjectForm-req', ' *') : null]);

    let input;
    switch (f.type) {
      case 'textarea':
        input = m('textarea.FormControl', { rows: 3, value: val, oninput: (e: any) => set(e.target.value) });
        break;
      case 'select':
        input = m('select.FormControl', { value: val, onchange: (e: any) => set(e.target.value) }, [
          m('option', { value: '' }, '—'),
          ...f.options.map((o) => m('option', { value: o }, o)),
        ]);
        break;
      case 'boolean':
        return m('.Form-group.ProjectForm-field', m(Switch, { state: val === '1', onchange: (v: boolean) => set(v ? '1' : '') }, [f.name]));
      case 'number':
        input = m('input.FormControl', { type: 'number', value: val, oninput: (e: any) => set(e.target.value) });
        break;
      case 'date':
        input = m('input.FormControl', { type: 'date', value: val, oninput: (e: any) => set(e.target.value) });
        break;
      case 'url':
        input = m('input.FormControl', { type: 'url', placeholder: 'https://', value: val, oninput: (e: any) => set(e.target.value) });
        break;
      default:
        input = m('input.FormControl', { value: val, oninput: (e: any) => set(e.target.value) });
    }

    return m('.Form-group.ProjectForm-field', [label, input]);
  }

  buttonsSection() {
    return m('.ProjectForm-buttons', [
      m('label.ProjectForm-sectionLabel', t('form.links_label')),
      ...this.cfg.buttons.map((b) => this.buttonInput(b)),
    ]);
  }

  buttonInput(b: ButtonDef) {
    const entry = this.links[b.id] || (this.links[b.id] = { url: '', label: '' });
    const domainHint = b.allowedDomains.length ? t('form.domain_hint', { domains: b.allowedDomains.join(', ') }) : null;

    return m('.Form-group.ProjectForm-link', [
      m('label', [b.icon ? m('i', { className: b.icon }) : null, ' ', b.label, b.isRequired ? m('span.ProjectForm-req', ' *') : null]),
      m('.ProjectForm-linkRow', [
        m('input.FormControl', { type: 'url', placeholder: 'https://', value: entry.url, oninput: (e: any) => (entry.url = e.target.value) }),
        b.allowCustomLabel
          ? m('input.FormControl.ProjectForm-linkLabel', { placeholder: t('form.link_label_placeholder'), value: entry.label, oninput: (e: any) => (entry.label = e.target.value) })
          : null,
      ]),
      domainHint ? m('span.helpText', domainHint) : null,
    ]);
  }

  toggleCategory(id: number) {
    const i = this.categoryIds.indexOf(id);
    if (i >= 0) this.categoryIds.splice(i, 1);
    else this.categoryIds.push(id);
    if (!this.categoryIds.includes(this.primaryCategoryId as number)) {
      this.primaryCategoryId = this.categoryIds[0] || null;
    }
  }

  upload(file?: File) {
    if (!file) return;
    this.uploading = true;
    uploadImage(file)
      .then((url) => { this.image = url; this.uploading = false; m.redraw(); })
      .catch((err) => { this.uploading = false; this.onerror(err); m.redraw(); });
  }

  submit() {
    this.loading = true;

    const links = Object.entries(this.links)
      .filter(([, v]) => v.url.trim() !== '')
      .map(([buttonId, v]) => ({ buttonId: Number(buttonId), url: v.url.trim(), label: v.label.trim() }));

    const attrs = {
      title: this.titleText.trim(),
      excerpt: this.excerpt.trim(),
      content: this.contentText,
      image: this.image || '',
      categoryIds: this.categoryIds,
      primaryCategoryId: this.primaryCategoryId,
      fieldValues: this.fieldValues,
      links,
      discussionId: this.discussionId || null,
    };

    saveProject(attrs, this.editing?.id)
      .then((res) => {
        this.loading = false;
        this.hide();
        if (this.attrs.onsave) this.attrs.onsave(res.data);
      })
      .catch((err) => {
        this.loading = false;
        this.onerror(err);
        m.redraw();
      });
  }
}
