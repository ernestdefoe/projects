import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import { saveCategory, type CategoryDef } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.admin.' + k, p);

export default class CategoryEditModal extends Modal {
  item: Partial<CategoryDef> = {};
  loading = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.item = { color: '#5b3df5', ...(this.attrs.item || {}) };
  }

  className() {
    return 'Modal--small ProjectsCategoryModal';
  }

  title() {
    return this.attrs.item ? t('config.category_edit') : t('config.category_new');
  }

  content() {
    return m('.Modal-body', [
      this.field('name', t('config.field_name'), 'text'),
      this.field('icon', t('config.field_icon'), 'text', 'fas fa-book'),
      m('.Form-group', [
        m('label', t('config.field_color')),
        m('input', { type: 'color', value: this.item.color || '#5b3df5', oninput: (e: any) => (this.item.color = e.target.value) }),
      ]),
      this.field('description', t('config.field_description'), 'text'),
      m('.Form-group', [
        m('label', t('config.category_badge')),
        m('input.FormControl', {
          type: 'number',
          placeholder: '—',
          value: this.item.badgeId || '',
          oninput: (e: any) => (this.item.badgeId = e.target.value ? Number(e.target.value) : null),
        }),
        m('span.helpText', t('config.category_badge_help')),
      ]),
      m('.Form-group', Button.component({ className: 'Button Button--primary Button--block', loading: this.loading, onclick: () => this.submit() }, t('config.save'))),
    ]);
  }

  field(key: keyof CategoryDef, label: string, type = 'text', placeholder = '') {
    return m('.Form-group', [
      m('label', label),
      m('input.FormControl', {
        type,
        placeholder,
        value: (this.item as any)[key] || '',
        oninput: (e: any) => ((this.item as any)[key] = e.target.value),
      }),
    ]);
  }

  submit() {
    this.loading = true;
    saveCategory(
      {
        name: this.item.name,
        icon: this.item.icon,
        color: this.item.color,
        description: this.item.description,
        badgeId: this.item.badgeId,
        position: this.item.position,
      },
      (this.attrs.item as CategoryDef | undefined)?.id
    )
      .then(() => {
        this.loading = false;
        this.hide();
        this.attrs.onsave && this.attrs.onsave();
      })
      .catch((e: any) => {
        this.loading = false;
        this.onerror(e);
        m.redraw();
      });
  }
}
