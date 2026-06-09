import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CategoryEditModal from './CategoryEditModal';
import FieldEditModal from './FieldEditModal';
import ButtonEditModal from './ButtonEditModal';
import {
  getConfig,
  deleteCategory,
  deleteField,
  deleteButton,
  type ProjectsConfig,
  type CategoryDef,
  type FieldDef,
  type ButtonDef,
} from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.admin.' + k, p);

/**
 * The category / custom-field / button-slot management UI, rendered on the
 * extension's settings page. Each section lists its items with edit + delete
 * controls and an "add" button that opens the matching modal.
 */
export default class ProjectsConfigManager extends Component {
  config: ProjectsConfig = { categories: [], fields: [], buttons: [] };
  loading = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.refresh();
  }

  refresh() {
    this.loading = true;
    getConfig()
      .then((res) => {
        this.config = res.data;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) {
      return m('.ProjectsConfig', m(LoadingIndicator));
    }

    return m('.ProjectsConfig', [
      this.section(
        'categories',
        t('config.categories_title'),
        t('config.categories_help'),
        this.config.categories,
        (c: CategoryDef) => [c.icon ? m('i', { className: c.icon, style: { color: c.color } }) : null, ' ', c.name],
        (item?: CategoryDef) => CategoryEditModal,
        (id: number) => deleteCategory(id)
      ),
      this.section(
        'fields',
        t('config.fields_title'),
        t('config.fields_help'),
        this.config.fields,
        (f: FieldDef) => [f.icon ? m('i', { className: f.icon }) : null, ' ', f.name, m('span.ProjectsConfig-meta', ' · ' + t('config.types.' + f.type)), f.isRequired ? m('span.ProjectsConfig-req', ' *') : null],
        () => FieldEditModal,
        (id: number) => deleteField(id)
      ),
      this.section(
        'buttons',
        t('config.buttons_title'),
        t('config.buttons_help'),
        this.config.buttons,
        (b: ButtonDef) => [b.icon ? m('i', { className: b.icon }) : null, ' ', b.label, b.allowedDomains.length ? m('span.ProjectsConfig-meta', ' · ' + b.allowedDomains.join(', ')) : null],
        () => ButtonEditModal,
        (id: number) => deleteButton(id)
      ),
    ]);
  }

  section(key: string, title: string, help: string, items: any[], renderLabel: (i: any) => any, modalFor: (i?: any) => any, remove: (id: number) => Promise<void>) {
    return m('.ProjectsConfig-section', [
      m('.ProjectsConfig-sectionHead', [m('h3', title), m('span.helpText', help)]),
      m('ul.ProjectsConfig-list', items.length
        ? items.map((item) =>
            m('li.ProjectsConfig-item', [
              m('span.ProjectsConfig-label', renderLabel(item)),
              m('.ProjectsConfig-itemActions', [
                Button.component({ className: 'Button Button--icon Button--text', icon: 'fas fa-pencil', onclick: () => this.edit(modalFor(item), item) }),
                Button.component({ className: 'Button Button--icon Button--text', icon: 'fas fa-trash', onclick: () => this.confirmDelete(remove, item.id) }),
              ]),
            ])
          )
        : m('li.ProjectsConfig-empty', t('config.none'))),
      Button.component({ className: 'Button Button--icon', icon: 'fas fa-plus', onclick: () => this.edit(modalFor(), undefined) }, t('config.add')),
    ]);
  }

  edit(modal: any, item?: any) {
    app.modal.show(modal, { item, onsave: () => this.refresh() });
  }

  confirmDelete(remove: (id: number) => Promise<void>, id: number) {
    if (!confirm(t('config.confirm_delete') as unknown as string)) return;
    remove(id).then(() => this.refresh());
  }
}
