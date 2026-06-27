import app from 'flarum/admin/app';
import type { CategoryDef } from '../common/api';

declare const m: any;
const t = (k: string): any => app.translator.trans('ernestdefoe-projects.admin.' + k);

/**
 * A category multi-select for restricting a field/button to specific categories.
 * Mutates item.categoryIds in place. An empty selection means "all categories".
 * Returns null when no categories exist yet.
 */
export function categoryRestrictionField(categories: CategoryDef[] | undefined, item: { categoryIds?: number[] }) {
  if (!categories || !categories.length) return null;

  const selected: number[] = item.categoryIds || (item.categoryIds = []);

  return m('.Form-group', [
    m('label', t('config.restrict_categories')),
    m('span.helpText', t('config.restrict_categories_help')),
    m(
      '.ProjectsConfig-catPick',
      categories.map((c) => {
        const on = selected.includes(c.id);
        return m(
          'button.ProjectsConfig-catChip' + (on ? '.is-on' : ''),
          {
            type: 'button',
            style: c.color ? { '--project-accent': c.color } : undefined,
            onclick: () => {
              const i = selected.indexOf(c.id);
              if (i >= 0) selected.splice(i, 1);
              else selected.push(c.id);
            },
          },
          [c.icon ? m('i', { className: c.icon }) : null, ' ', c.name]
        );
      })
    ),
  ]);
}
