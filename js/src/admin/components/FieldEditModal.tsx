import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import { saveField, type FieldDef, type FieldType } from '../../common/api';
import { categoryRestrictionField } from '../categoryRestriction';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.admin.' + k, p);
const TYPES: FieldType[] = ['text', 'textarea', 'number', 'date', 'url', 'select', 'boolean'];

export default class FieldEditModal extends Modal {
  item: Partial<FieldDef> = {};
  optionsText = '';
  loading = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.item = { type: 'text', isRequired: false, onCard: true, ...(this.attrs.item || {}) };
    this.optionsText = ((this.item.options as string[]) || []).join('\n');
  }

  className() {
    return 'Modal--small ProjectsFieldModal';
  }

  title() {
    return this.attrs.item ? t('config.field_edit') : t('config.field_new');
  }

  content() {
    return m('.Modal-body', [
      this.text('name', t('config.field_name')),
      m('.Form-group', [
        m('label', t('config.field_type')),
        m('select.FormControl', { value: this.item.type, onchange: (e: any) => (this.item.type = e.target.value) },
          TYPES.map((ty) => m('option', { value: ty }, t('config.types.' + ty)))),
      ]),
      this.item.type === 'select'
        ? m('.Form-group', [
            m('label', t('config.field_options')),
            m('textarea.FormControl', { rows: 4, placeholder: t('config.field_options_help'), value: this.optionsText, oninput: (e: any) => (this.optionsText = e.target.value) }),
          ])
        : null,
      this.text('icon', t('config.field_icon'), 'fas fa-tag'),
      this.text('prefix', t('config.field_prefix')),
      this.text('suffix', t('config.field_suffix')),
      m('.Form-group', m(Switch, { state: !!this.item.isRequired, onchange: (v: boolean) => (this.item.isRequired = v) }, t('config.field_required'))),
      m('.Form-group', m(Switch, { state: this.item.onCard !== false, onchange: (v: boolean) => (this.item.onCard = v) }, t('config.field_on_card'))),
      categoryRestrictionField(this.attrs.categories, this.item),
      m('.Form-group', Button.component({ className: 'Button Button--primary Button--block', loading: this.loading, onclick: () => this.submit() }, t('config.save'))),
    ]);
  }

  text(key: keyof FieldDef, label: string, placeholder = '') {
    return m('.Form-group', [
      m('label', label),
      m('input.FormControl', { placeholder, value: (this.item as any)[key] || '', oninput: (e: any) => ((this.item as any)[key] = e.target.value) }),
    ]);
  }

  submit() {
    this.loading = true;
    const options = this.optionsText.split('\n').map((s) => s.trim()).filter(Boolean);
    saveField(
      {
        name: this.item.name,
        type: this.item.type,
        options,
        icon: this.item.icon,
        prefix: this.item.prefix,
        suffix: this.item.suffix,
        isRequired: this.item.isRequired,
        onCard: this.item.onCard,
        categoryIds: this.item.categoryIds || [],
        position: this.item.position,
      },
      (this.attrs.item as FieldDef | undefined)?.id
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
