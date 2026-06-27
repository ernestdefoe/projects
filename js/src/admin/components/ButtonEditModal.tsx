import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import { saveButton, type ButtonDef } from '../../common/api';
import { categoryRestrictionField } from '../categoryRestriction';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.admin.' + k, p);

export default class ButtonEditModal extends Modal {
  item: Partial<ButtonDef> = {};
  domainsText = '';
  loading = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.item = { allowCustomLabel: true, isRequired: false, isPrimary: false, ...(this.attrs.item || {}) };
    this.domainsText = ((this.item.allowedDomains as string[]) || []).join('\n');
  }

  className() {
    return 'Modal--small ProjectsButtonModal';
  }

  title() {
    return this.attrs.item ? t('config.button_edit') : t('config.button_new');
  }

  content() {
    return m('.Modal-body', [
      this.text('label', t('config.button_label_field')),
      this.text('icon', t('config.field_icon'), 'fab fa-youtube'),
      m('.Form-group', [
        m('label', t('config.button_domains')),
        m('textarea.FormControl', { rows: 3, placeholder: 'youtube.com\nyoutu.be', value: this.domainsText, oninput: (e: any) => (this.domainsText = e.target.value) }),
        m('span.helpText', t('config.button_domains_help')),
      ]),
      m('.Form-group', m(Switch, { state: this.item.allowCustomLabel !== false, onchange: (v: boolean) => (this.item.allowCustomLabel = v) }, t('config.button_custom_label'))),
      m('.Form-group', m(Switch, { state: !!this.item.isPrimary, onchange: (v: boolean) => (this.item.isPrimary = v) }, t('config.button_primary'))),
      m('.Form-group', m(Switch, { state: !!this.item.isRequired, onchange: (v: boolean) => (this.item.isRequired = v) }, t('config.button_required'))),
      categoryRestrictionField(this.attrs.categories, this.item),
      m('.Form-group', Button.component({ className: 'Button Button--primary Button--block', loading: this.loading, onclick: () => this.submit() }, t('config.save'))),
    ]);
  }

  text(key: keyof ButtonDef, label: string, placeholder = '') {
    return m('.Form-group', [
      m('label', label),
      m('input.FormControl', { placeholder, value: (this.item as any)[key] || '', oninput: (e: any) => ((this.item as any)[key] = e.target.value) }),
    ]);
  }

  submit() {
    this.loading = true;
    const allowedDomains = this.domainsText.split('\n').map((s) => s.trim()).filter(Boolean);
    saveButton(
      {
        label: this.item.label,
        icon: this.item.icon,
        allowedDomains,
        allowCustomLabel: this.item.allowCustomLabel,
        isRequired: this.item.isRequired,
        isPrimary: this.item.isPrimary,
        categoryIds: this.item.categoryIds || [],
        position: this.item.position,
      },
      (this.attrs.item as ButtonDef | undefined)?.id
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
